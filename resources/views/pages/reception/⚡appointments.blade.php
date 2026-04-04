<?php

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\QueueStatus;
use App\Enums\QueueTokenStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\Visit;
use App\Models\VisitService;
use App\Services\DoctorShareCalculator;
use App\Services\VeevoTechSmsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appointments')] class extends Component
{
    private const int PHONE_DIGITS = 11;

    private const int SLOT_MINUTES = 5;

    /** Fixed number of 5-minute blocks on the grid (slot #n ↔ token T-n when shown). */
    private const int GRID_SLOT_COUNT = 50;

    /** Appointments always use Consultation (see appinfo / product rules). */
    private const int CONSULTATION_SERVICE_ID = 1;

    public ?int $filterDoctorId = null;

    public bool $showBookModal = false;

    public ?int $bookDoctorId = null;

    public string $bookSlotTime = '';

    public string $phoneQuery = '';

    public int $phoneFieldVersion = 0;

    public ?int $familyId = null;

    public ?int $selectedPatientId = null;

    public string $bookNotes = '';

    public bool $showNewFamilyModal = false;

    public string $newHeadName = '';

    public string $newHeadGender = 'male';

    public bool $showNewMemberModal = false;

    public string $newMemberName = '';

    public string $newMemberGender = 'male';

    public bool $showBroadcastModal = false;

    public string $broadcastBody = '';

    public function mount(): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
            abort(403);
        }
    }

    public function todayDate(): string
    {
        return now()->format('Y-m-d');
    }

    #[Computed]
    public function activeShift(): ?Shift
    {
        return Shift::query()
            ->where('status', ShiftStatus::Open)
            ->first();
    }

    #[Computed]
    public function family(): ?Family
    {
        return $this->familyId
            ? Family::query()->with(['patients' => fn ($q) => $q->orderBy('type')->orderBy('name')])->find($this->familyId)
            : null;
    }

    /**
     * Doctors eligible for the consultation appointments dropdown (hours + consultation price).
     *
     * @return \Illuminate\Support\Collection<int, Doctor>
     */
    #[Computed]
    public function doctorsForSelect()
    {
        return Doctor::query()
            ->where('status', 'active')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereHas(
                'servicePrices',
                fn ($q) => $q->where('service_id', self::CONSULTATION_SERVICE_ID)
                    ->where('is_active', true)
                    ->whereHas('service', fn ($s) => $s->where('is_active', true)->where('is_standalone', false))
            )
            ->orderBy('name')
            ->get()
            ->filter(fn (Doctor $d) => $d->hasWorkingHours())
            ->values();
    }

    /**
     * The single doctor whose grid is shown — only after the user picks one in the dropdown.
     *
     * @return \Illuminate\Support\Collection<int, Doctor>
     */
    #[Computed]
    public function calendarDoctors()
    {
        if (! $this->filterDoctorId) {
            return collect();
        }

        $doc = Doctor::query()
            ->whereKey($this->filterDoctorId)
            ->where('status', 'active')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereHas(
                'servicePrices',
                fn ($q) => $q->where('service_id', self::CONSULTATION_SERVICE_ID)
                    ->where('is_active', true)
                    ->whereHas('service', fn ($s) => $s->where('is_active', true)->where('is_standalone', false))
            )
            ->first();

        if (! $doc || ! $doc->hasWorkingHours()) {
            return collect();
        }

        return collect([$doc]);
    }

    #[Computed]
    public function appointmentsByDoctorAndSlot(): array
    {
        if (! $this->filterDoctorId) {
            return [];
        }

        $query = Appointment::query()
            ->where('service_id', self::CONSULTATION_SERVICE_ID)
            ->where('doctor_id', $this->filterDoctorId)
            ->whereDate('appointment_date', $this->todayDate())
            ->whereIn('status', [
                AppointmentStatus::Booked,
                AppointmentStatus::Arrived,
            ])
            ->with(['patient', 'doctor', 'service', 'queueToken']);

        $map = [];

        foreach ($query->get() as $apt) {
            $slot = $this->slotBucketFromTimeString($apt->appointment_time);
            $key = (int) $apt->doctor_id;
            $map[$key] ??= [];
            $map[$key][$slot] = $apt;
        }

        return $map;
    }

    /**
     * All walk-in queue tokens for the selected doctor today (no appointment), same scope as the queue desk.
     * The summary table lists all; the grid maps each token to a cell by token number (T-n → slot n), not by issue time.
     *
     * @return Collection<int, QueueToken>
     */
    #[Computed]
    public function walkInTokensForDoctorToday(): Collection
    {
        if (! $this->filterDoctorId) {
            return collect();
        }

        $shift = $this->activeShift;
        if (! $shift) {
            return collect();
        }

        $dayStart = now()->startOfDay();
        $dayEnd = now()->endOfDay();

        /**
         * Walk-in tokens: no appointment, issued today, on this doctor's active queue for the open shift.
         * Match Queue / walk-in flows: queue must be active (not closed) and tied to the current shift.
         */
        return QueueToken::query()
            ->whereNull('appointment_id')
            ->whereIn('status', [
                QueueTokenStatus::Waiting,
                QueueTokenStatus::Serving,
                QueueTokenStatus::Done,
                QueueTokenStatus::Skipped,
            ])
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->whereHas('queue', function ($q) use ($shift): void {
                $q->where('doctor_id', $this->filterDoctorId)
                    ->where('shift_id', $shift->id)
                    ->whereNull('closed_at')
                    ->where('status', QueueStatus::Active);
            })
            ->with(['queue', 'patient'])
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function walkInTokensByDoctorAndSlot(): array
    {
        $map = [];

        $doctor = Doctor::query()->find($this->filterDoctorId);
        if (! $doctor) {
            return [];
        }

        $slotTimes = $this->slotTimesForDoctorModel($doctor);
        if ($slotTimes === []) {
            return [];
        }

        foreach ($this->walkInTokensForDoctorToday as $t) {
            $doctorId = (int) ($t->queue?->doctor_id ?? 0);
            if ($doctorId === 0) {
                continue;
            }
            // T-n maps to grid slot n (1-based); times are labels only (+5 min per slot from doctor start).
            $n = max(1, (int) $t->token_number);
            $idx = min($n, self::GRID_SLOT_COUNT) - 1;
            $slot = $slotTimes[$idx];
            $map[$doctorId] ??= [];
            if (! isset($map[$doctorId][$slot])) {
                $map[$doctorId][$slot] = $t;
            }
        }

        return $map;
    }

    /**
     * Ordered slot keys (HH:mm) for this doctor's reception window — used by the 5-column grid.
     *
     * @return list<string>
     */
    public function slotsForDoctor(Doctor $doc): array
    {
        return $this->slotTimesForDoctorModel($doc);
    }

    public function updatedFilterDoctorId(mixed $value): void
    {
        // Empty option value="" / "0" — keep backend null so UI and server stay aligned (single real option is not auto-selected).
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            $this->filterDoctorId = null;

            return;
        }

        $this->filterDoctorId = (int) $value;
    }

    public function updatedShowBookModal(bool $open): void
    {
        if (! $open) {
            $this->resetBookFormPartial();
        }
    }

    public function openBookSlot(int $doctorId, string $slotTime): void
    {
        if ($this->filterDoctorId !== null && $doctorId !== $this->filterDoctorId) {
            return;
        }

        $doctor = Doctor::query()->find($doctorId);
        if (! $doctor || ! $this->doctorWorksAtSlot($doctor, $slotTime)) {
            return;
        }

        if ($this->slotTaken($doctorId, $slotTime)) {
            return;
        }

        $this->bookDoctorId = $doctorId;
        $this->bookSlotTime = $slotTime;
        $this->bookNotes = '';
        $this->resetErrorBag();
        $this->showBookModal = true;
    }

    public function lookupPhone(): void
    {
        $this->validate($this->phoneQueryRules(), [], [
            'phoneQuery' => __('phone'),
        ]);

        $phone = $this->normalizePhone($this->phoneQuery);

        $family = Family::query()->where('phone', $phone)->first();

        $this->familyId = $family?->id;
        $this->selectedPatientId = null;

        if (! $family) {
            $this->showNewFamilyModal = true;
        }
    }

    public function clearFamily(): void
    {
        $this->familyId = null;
        $this->selectedPatientId = null;
        $this->phoneQuery = '';
        $this->phoneFieldVersion++;
        unset($this->family);
        $this->resetErrorBag();
    }

    public function selectPatient(int $patientId): void
    {
        if (! $this->family || ! $this->family->patients->contains('id', $patientId)) {
            return;
        }

        $this->selectedPatientId = $patientId;
    }

    public function openNewMemberModal(): void
    {
        if (! $this->family) {
            return;
        }

        $this->newMemberName = '';
        $this->newMemberGender = 'male';
        $this->showNewMemberModal = true;
    }

    public function registerNewFamily(): void
    {
        $this->validate($this->phoneQueryRules(), [], [
            'phoneQuery' => __('phone'),
        ]);

        $phone = $this->normalizePhone($this->phoneQuery);

        $validated = $this->validate([
            'newHeadName' => ['required', 'string', 'max:255'],
            'newHeadGender' => ['required', 'in:male,female,other'],
        ], [], [
            'newHeadName' => __('name'),
            'newHeadGender' => __('gender'),
        ]);

        if (Family::query()->where('phone', $phone)->exists()) {
            $this->addError('phoneQuery', __('This phone is already registered. Search again.'));

            return;
        }

        $family = DB::transaction(function () use ($phone, $validated) {
            $family = Family::query()->create(['phone' => $phone]);

            $head = Patient::query()->create([
                'family_id' => $family->id,
                'name' => $validated['newHeadName'],
                'gender' => $validated['newHeadGender'],
                'type' => PatientType::Head,
                'relation_to_head' => null,
            ]);

            $family->update(['head_id' => $head->id]);

            return $family->fresh(['patients']);
        });

        $this->familyId = $family->id;
        $this->selectedPatientId = $family->head_id;
        $this->showNewFamilyModal = false;
        $this->newHeadName = '';
        $this->newHeadGender = 'male';
        unset($this->family);
        $this->resetErrorBag();
    }

    public function addFamilyMember(): void
    {
        $family = $this->family;

        if (! $family) {
            return;
        }

        $validated = $this->validate([
            'newMemberName' => ['required', 'string', 'max:255'],
            'newMemberGender' => ['required', 'in:male,female,other'],
        ], [], [
            'newMemberName' => __('name'),
            'newMemberGender' => __('gender'),
        ]);

        $patient = Patient::query()->create([
            'family_id' => $family->id,
            'name' => $validated['newMemberName'],
            'gender' => $validated['newMemberGender'],
            'type' => PatientType::Member,
            'relation_to_head' => null,
        ]);

        $this->selectedPatientId = $patient->id;
        $this->showNewMemberModal = false;
        $this->newMemberName = '';
        $this->newMemberGender = 'male';
        unset($this->family);
        $this->resetErrorBag();
    }

    public function confirmBook(): void
    {
        $shift = $this->activeShift;

        if (! $shift) {
            $this->addError('book', __('Open a shift before booking.'));

            return;
        }

        if ($this->slotTaken((int) $this->bookDoctorId, $this->bookSlotTime)) {
            $this->addError('book', __('This slot was just taken. Refresh and pick another time.'));

            return;
        }

        $this->validate([
            'bookDoctorId' => ['required', 'exists:doctors,id'],
            'bookSlotTime' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'selectedPatientId' => ['required', 'exists:patients,id'],
        ], [], [
            'bookDoctorId' => __('doctor'),
            'bookSlotTime' => __('time'),
            'selectedPatientId' => __('patient'),
        ]);

        $patient = Patient::query()->with('family')->findOrFail($this->selectedPatientId);

        $service = Service::query()->find(self::CONSULTATION_SERVICE_ID);

        if (! $service || ! $service->is_active) {
            $this->addError('book', __('Consultation (service #:id) is missing or inactive. Check Admin → Services.', ['id' => self::CONSULTATION_SERVICE_ID]));

            return;
        }

        if ($service->is_standalone) {
            $this->addError('book', __('Consultation must be a doctor-linked service for this flow.'));

            return;
        }

        $priceRow = $service->priceForDoctor($this->bookDoctorId);

        if (! $priceRow || ! $priceRow->is_active) {
            $this->addError('book', __('No active price for this doctor and service.'));

            return;
        }

        $timeForDb = $this->bookSlotTime.':00';

        try {
            $booked = DB::transaction(function () use ($shift, $patient, $service, $priceRow, $timeForDb): array {
                if ($this->appointmentBlocksSlot((int) $this->bookDoctorId, $this->todayDate(), $timeForDb)) {
                    throw new \RuntimeException('slot_taken');
                }

                $queue = $this->findOrCreateActiveQueue($service->id, $this->bookDoctorId, $shift->id);

                // Appointment reservations must reserve the exact token matching the grid slot:
                // slot #n ↔ token T-n (so booking slot 50 produces token 50).
                $doctor = Doctor::query()->find($this->bookDoctorId);
                if (! $doctor) {
                    throw new \RuntimeException('doctor_missing');
                }

                $slotTimes = $this->slotTimesForDoctorModel($doctor);
                $normalizedSlot = $this->normalizeSlotTime($this->bookSlotTime);
                $idx = array_search($normalizedSlot, $slotTimes, true);
                if ($idx === false) {
                    throw new \RuntimeException('slot_invalid');
                }

                $tokenNumber = $idx + 1;

                // Prevent collisions with any existing queue token number.
                // (This also protects against stale Livewire caches / concurrency.)
                $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);
                $tokenAlreadyExists = QueueToken::query()
                    ->where('queue_id', $queue->id)
                    ->where('token_number', $tokenNumber)
                    ->exists();

                if ($tokenAlreadyExists) {
                    throw new \RuntimeException('slot_taken');
                }

                $token = QueueToken::query()->create([
                    'queue_id' => $queue->id,
                    'patient_id' => $patient->id,
                    'token_number' => $tokenNumber,
                    'status' => QueueTokenStatus::Reserved,
                    'reserved_at' => now(),
                ]);

                // Keep the queue counter in sync with reserved grid tokens (e.g. slot 50 => next walk-in should start at 51).
                if ((int) $queue->current_token < $tokenNumber) {
                    $queue->update(['current_token' => $tokenNumber]);
                }

                $appointment = Appointment::query()->create([
                    'patient_id' => $patient->id,
                    'family_id' => $patient->family_id,
                    'doctor_id' => $this->bookDoctorId,
                    'service_id' => $service->id,
                    'created_by' => Auth::id(),
                    'appointment_date' => $this->todayDate(),
                    'appointment_time' => $timeForDb,
                    'status' => AppointmentStatus::Booked,
                    'notes' => $this->bookNotes !== '' ? $this->bookNotes : null,
                    'sms_sent' => false,
                    'queue_token_id' => $token->id,
                ]);

                $token->update(['appointment_id' => $appointment->id]);

                return [
                    'appointment_id' => $appointment->id,
                    'token_number' => $tokenNumber,
                ];
            });

            $familyPhone = $patient->family?->phone ?? '';
            $body = __('Your appointment: :service with :doctor on :date at :time. Token :token.', [
                'service' => $service->name,
                'doctor' => $priceRow->doctor?->name ?? '',
                'date' => Carbon::parse($this->todayDate())->translatedFormat('M j, Y'),
                'time' => Carbon::parse($this->todayDate().' '.$this->bookSlotTime.':00')->format('g:i a'),
                'token' => $booked['token_number'],
            ]);

            $sms = app(VeevoTechSmsService::class);
            $sent = $sms->sendToStoredPhone($familyPhone, $body);

            if ($sent) {
                Appointment::query()->whereKey($booked['appointment_id'])->update(['sms_sent' => true]);
            }
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'slot_taken') {
                $this->addError('book', __('That slot is no longer available.'));

                return;
            }
            throw $e;
        } catch (\Throwable) {
            $this->addError('book', __('Could not complete booking. Try again.'));

            return;
        }

        // slotTaken() warms #[Computed] caches before insert; clear so the render reloads appointments + queueToken.
        $this->forgetScheduleCaches();

        $this->showBookModal = false;
        $this->resetBookFormPartial();
        $this->clearFamily();
    }

    public function markArrived(int $appointmentId): void
    {
        $appointment = Appointment::query()
            ->with(['patient', 'doctor', 'service', 'queueToken', 'family'])
            ->find($appointmentId);

        if (! $appointment || $appointment->status !== AppointmentStatus::Booked) {
            return;
        }

        $shift = $this->activeShift;

        if (! $shift) {
            $this->addError('arrival', __('Open a shift before checking in.'));

            return;
        }

        $token = $appointment->queueToken;

        if (! $token || $token->status !== QueueTokenStatus::Reserved) {
            $this->addError('arrival', __('This booking has no active reserved token.'));

            return;
        }

        $invoiceId = null;

        try {
            DB::transaction(function () use ($appointment, $token, $shift, &$invoiceId): void {
                $patient = Patient::query()->with('family')->lockForUpdate()->findOrFail($appointment->patient_id);

                $token->refresh();

                if ($token->visit_id !== null || $token->status !== QueueTokenStatus::Reserved) {
                    throw new \RuntimeException('token_invalid');
                }

                $sp = ServicePrice::query()
                    ->where('service_id', $appointment->service_id)
                    ->where('doctor_id', $appointment->doctor_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $sp) {
                    throw new \RuntimeException('no_price');
                }

                $charged = (int) $sp->price;

                $visit = Visit::query()->create([
                    'patient_id' => $patient->id,
                    'family_id' => $patient->family_id,
                    'doctor_id' => $appointment->doctor_id,
                    'shift_id' => $shift->id,
                    'status' => VisitStatus::InProgress,
                ]);

                VisitService::query()->create([
                    'visit_id' => $visit->id,
                    'service_id' => $appointment->service_id,
                    'doctor_id' => $appointment->doctor_id,
                    'service_price_id' => $sp->id,
                    'queue_token_id' => $token->id,
                    'status' => 'pending',
                ]);

                $token->update([
                    'status' => QueueTokenStatus::Waiting,
                    'visit_id' => $visit->id,
                    'paid_at' => now(),
                ]);

                $invoice = Invoice::query()->create([
                    'visit_id' => $visit->id,
                    'patient_id' => $patient->id,
                    'shift_id' => $shift->id,
                    'total_amount' => $charged,
                    'discount' => 0,
                    'final_amount' => $charged,
                    'status' => InvoiceStatus::Paid,
                ]);

                $slipIndexToday = $sp->doctor_id
                    ? DoctorShareCalculator::countSlipsTodayForDoctor($appointment->doctor_id)
                    : 0;
                $docShare = DoctorShareCalculator::amountForLine($sp, $charged, $slipIndexToday);

                InvoiceService::query()->create([
                    'invoice_id' => $invoice->id,
                    'service_id' => $appointment->service_id,
                    'service_price_id' => $sp->id,
                    'doctor_id' => $appointment->doctor_id,
                    'price' => $charged,
                    'doctor_share_amount' => $docShare,
                    'discount' => 0,
                    'final_amount' => $charged,
                ]);

                $appointment->update(['status' => AppointmentStatus::Arrived]);

                $invoiceId = $invoice->id;
            });
        } catch (\RuntimeException $e) {
            if (in_array($e->getMessage(), ['token_invalid', 'no_price'], true)) {
                $this->addError('arrival', __('Cannot check in this booking. Refresh or contact support.'));

                return;
            }
            throw $e;
        }

        $this->forgetScheduleCaches();

        if ($invoiceId !== null) {
            $printUrl = route('invoices.print', ['invoice' => $invoiceId], absolute: true);
            $this->js('setTimeout(function(){ window.open('.Js::from($printUrl).', "_blank", "noopener,noreferrer"); }, 100)');
        }
    }

    public function cancelBooking(int $appointmentId): void
    {
        $appointment = Appointment::query()
            ->with(['queueToken'])
            ->find($appointmentId);

        if (! $appointment || $appointment->status !== AppointmentStatus::Booked) {
            return;
        }

        $token = $appointment->queueToken;

        DB::transaction(function () use ($appointment, $token): void {
            $appointment->update(['status' => AppointmentStatus::Cancelled]);

            if ($token && $token->status === QueueTokenStatus::Reserved && $token->visit_id === null) {
                $token->delete();
            }
        });

        $this->forgetScheduleCaches();
    }

    public function openBroadcastModal(): void
    {
        $this->broadcastBody = '';
        $this->resetErrorBag(['broadcastBody']);
        $this->showBroadcastModal = true;
    }

    public function sendBroadcast(): void
    {
        $this->validate([
            'broadcastBody' => ['required', 'string', 'max:2000'],
        ], [], [
            'broadcastBody' => __('message'),
        ]);

        $appointments = Appointment::query()
            ->where('service_id', self::CONSULTATION_SERVICE_ID)
            ->whereDate('appointment_date', $this->todayDate())
            ->where('status', AppointmentStatus::Booked)
            ->with('family')
            ->get();

        $phones = $appointments->pluck('family.phone')->filter()->unique()->values();

        $sms = app(VeevoTechSmsService::class);

        foreach ($phones as $phone) {
            $sms->sendToStoredPhone((string) $phone, $this->broadcastBody);
        }

        $this->showBroadcastModal = false;
        $this->broadcastBody = '';
    }

    protected function slotTaken(int $doctorId, string $slotTime): bool
    {
        $bucket = $this->slotBucketFromTimeString($slotTime);

        if (isset($this->appointmentsByDoctorAndSlot[$doctorId][$bucket])) {
            return true;
        }

        return isset($this->walkInTokensByDoctorAndSlot[$doctorId][$bucket]);
    }

    /** Clears request-scoped #[Computed] maps after DB changes (see confirmBook + slotTaken cache ordering). */
    protected function forgetScheduleCaches(): void
    {
        unset($this->appointmentsByDoctorAndSlot, $this->walkInTokensByDoctorAndSlot, $this->walkInTokensForDoctorToday);
    }

    /** Whether this token number fits the fixed grid (T-1…T-:max). */
    public function walkInTokenMapsToDoctorGrid(Doctor $doc, QueueToken $token): bool
    {
        return (int) $token->token_number >= 1
            && (int) $token->token_number <= self::GRID_SLOT_COUNT
            && $this->slotTimesForDoctorModel($doc) !== [];
    }

    public function queueTokenStatusLabel(QueueToken $token): string
    {
        return match ($token->status) {
            QueueTokenStatus::Waiting => __('waiting'),
            QueueTokenStatus::Serving => __('serving'),
            QueueTokenStatus::Done => __('done'),
            QueueTokenStatus::Skipped => __('skipped'),
            QueueTokenStatus::Reserved => __('reserved'),
        };
    }

    protected function appointmentBlocksSlot(int $doctorId, string $date, string $timeHms): bool
    {
        $bucket = $this->slotBucketFromTimeString($timeHms);

        return Appointment::query()
            ->where('service_id', self::CONSULTATION_SERVICE_ID)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', [
                AppointmentStatus::Booked,
                AppointmentStatus::Arrived,
            ])
            ->get()
            ->contains(fn (Appointment $a) => $this->slotBucketFromTimeString($a->appointment_time) === $bucket);
    }

    protected function normalizeSlotTime(mixed $value): string
    {
        return Carbon::parse($value)->format('H:i');
    }

    /**
     * Exactly {@see GRID_SLOT_COUNT} slots: +5 minutes per block from doctor start (labels only).
     *
     * @return list<string>
     */
    protected function slotTimesForDoctorModel(Doctor $doc): array
    {
        if (! $doc->hasWorkingHours()) {
            return [];
        }

        $day = $this->todayDate();
        $start = Carbon::parse($day.' '.$this->timeStringForDoctor($doc->start_time));

        $out = [];
        for ($i = 0; $i < self::GRID_SLOT_COUNT; $i++) {
            $out[] = $start->copy()->addMinutes($i * self::SLOT_MINUTES)->format('H:i');
        }

        return $out;
    }

    protected function timeStringForDoctor(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '00:00:00';
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('H:i:s');
        }
        $s = trim((string) $value);
        if (preg_match('/^\d{2}:\d{2}$/', $s) === 1) {
            return $s.':00';
        }

        return $s;
    }

    protected function slotBucketFromTimeString(mixed $time): string
    {
        $c = Carbon::parse($time);
        $h = (int) $c->format('H');
        $m = (int) $c->format('i');
        $bucket = intdiv($m, self::SLOT_MINUTES) * self::SLOT_MINUTES;

        return sprintf('%02d:%02d', $h, $bucket);
    }

    public function doctorWorksAtSlot(Doctor $doc, string $slot): bool
    {
        return in_array($slot, $this->slotTimesForDoctorModel($doc), true);
    }

    protected function findOrCreateActiveQueue(int $serviceId, ?int $doctorId, int $shiftId): Queue
    {
        return Queue::findOrCreateActiveForShift($serviceId, $doctorId, $shiftId);
    }

    /**
     * @return array<string, list<string|\Closure>>
     */
    protected function phoneQueryRules(): array
    {
        return [
            'phoneQuery' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $digits = $this->phoneDigitsOnly((string) $value);
                    if (strlen($digits) !== self::PHONE_DIGITS) {
                        $fail(__('Enter exactly :need digits (:current entered).', [
                            'need' => self::PHONE_DIGITS,
                            'current' => strlen($digits),
                        ]));
                    }
                },
            ],
        ];
    }

    protected function phoneDigitsOnly(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    protected function normalizePhone(string $phone): string
    {
        return $this->phoneDigitsOnly($phone);
    }

    protected function resetBookFormPartial(): void
    {
        $this->bookDoctorId = null;
        $this->bookSlotTime = '';
        $this->bookServiceId = null;
        $this->bookNotes = '';
        $this->resetErrorBag(['book', 'selectedPatientId']);
    }

    /** 12-hour label for a slot key (HH:mm) on today's date. */
    public function slotHumanTime(string $slot): string
    {
        return Carbon::parse($this->todayDate().' '.$slot.':00')->format('g:i A');
    }

    public function appointmentTime12h(mixed $appointmentTime): string
    {
        return Carbon::parse($appointmentTime)->format('g:i A');
    }

    public function walkInTime12h(\Carbon\CarbonInterface $dt): string
    {
        return $dt->format('g:i A');
    }

    protected function cellClasses(Appointment $apt): string
    {
        return match ($apt->status) {
            AppointmentStatus::Booked => 'border-rose-300 bg-rose-50/95 text-rose-950 ring-1 ring-rose-200/90 dark:border-rose-800/80 dark:bg-rose-950/40 dark:text-rose-50 dark:ring-rose-900/50',
            AppointmentStatus::Arrived => 'border-sky-300 bg-sky-50/95 text-sky-950 ring-1 ring-sky-200/90 dark:border-sky-800/80 dark:bg-sky-950/40 dark:text-sky-50 dark:ring-sky-900/50',
            default => 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
        };
    }

    protected function walkInCellClasses(): string
    {
        return 'border-zinc-300 bg-zinc-200/85 text-zinc-900 ring-1 ring-zinc-300/80 dark:border-zinc-600 dark:bg-zinc-800/85 dark:text-zinc-100 dark:ring-zinc-600/50';
    }
}; ?>

<div class="appointments-page mx-auto max-w-[100rem] space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-violet-50 via-white to-cyan-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-violet-950/30 dark:via-zinc-900 dark:to-cyan-950/20">
        <div class="pointer-events-none absolute -end-16 -top-20 size-48 rounded-full bg-violet-400/20 blur-3xl dark:bg-violet-500/10"></div>
        <div class="pointer-events-none absolute -bottom-24 -start-20 size-56 rounded-full bg-cyan-400/15 blur-3xl dark:bg-cyan-500/10"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __("Today's Appointments") }} — {{ now()->translatedFormat('l, M j, Y') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Consultation only (service #:id). Reserve queue tokens, SMS is logged, then mark as arrived to invoice and print. Grey cells are walk-in consultation tokens without an appointment, matched by issue time.', ['id' => 1]) }}
                </flux:text>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                @if ($this->activeShift)
                    <flux:badge color="lime">{{ __('Shift open') }}</flux:badge>
                @else
                    <flux:badge color="zinc">{{ __('No active shift') }}</flux:badge>
                @endif
                <flux:button variant="outline" size="sm" icon="megaphone" type="button" wire:click="openBroadcastModal">
                    {{ __('Broadcast to booked') }}
                </flux:button>
            </div>
        </div>
    </header>

    @if (! $this->activeShift)
        <flux:callout color="amber" icon="exclamation-triangle">
            {{ __('You need an open shift to book new appointments or check patients in.') }}
        </flux:callout>
    @endif

    <flux:error name="arrival" />

    <flux:card class="space-y-6 p-6 sm:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-4 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end lg:gap-6">
                <flux:field class="min-w-[14rem]">
                    <flux:label>{{ __('Doctor') }}</flux:label>
                    <flux:select wire:model.live="filterDoctorId">
                        <flux:select.option value="">{{ __('Choose a doctor…') }}</flux:select.option>
                        @foreach ($this->doctorsForSelect as $doc)
                            <flux:select.option value="{{ $doc->id }}">{{ $doc->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Pick one doctor — the schedule loads only for them.') }}</flux:text>
                </flux:field>
            </div>
            <div class="flex flex-wrap gap-3 text-xs text-zinc-600 dark:text-zinc-400">
                <span class="inline-flex items-center gap-2">
                    <span class="size-3 rounded-sm bg-emerald-400/90 ring-1 ring-emerald-600/20"></span>
                    {{ __('Available') }}
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="size-3 rounded-sm bg-rose-400/90 ring-1 ring-rose-600/20"></span>
                    {{ __('Booked') }}
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="size-3 rounded-sm bg-sky-400/90 ring-1 ring-sky-600/20"></span>
                    {{ __('Arrived') }}
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="size-3 rounded-sm bg-zinc-400/90 ring-1 ring-zinc-600/30"></span>
                    {{ __('Used by walk-in') }}
                </span>
            </div>
        </div>

        @if ($this->doctorsForSelect->isEmpty())
            <flux:callout color="zinc" icon="information-circle">
                {{ __('No doctors with working hours (start/end) and consultation pricing. Set start & end times on each doctor and service prices in Admin.') }}
            </flux:callout>
        @elseif (! $filterDoctorId)
            <flux:callout color="zinc" icon="user-circle">
                {{ __('Select a doctor above to view today’s consultation slots. Nothing is shown until you choose one.') }}
            </flux:callout>
        @elseif ($this->calendarDoctors->isEmpty())
            <flux:callout color="amber" icon="exclamation-triangle">
                {{ __('That doctor is not available for consultation on this screen. Pick another.') }}
            </flux:callout>
        @else
            <div class="space-y-10">
                @foreach ($this->calendarDoctors as $doc)
                    @php
                        $slots = $this->slotsForDoctor($doc);
                        $slotCount = count($slots);
                    @endphp
                    <section wire:key="doctor-section-{{ $doc->id }}" class="space-y-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
                            <flux:heading size="md" class="text-zinc-900 dark:text-white">{{ $doc->name }}</flux:heading>
                            <flux:text class="text-xs text-zinc-500">
                                {{ __(':count slots (5 min each) · five per row · slot #n ↔ token T-n for walk-ins', ['count' => $slotCount]) }}
                            </flux:text>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-zinc-200/80 p-2 dark:border-zinc-700">
                            <div class="grid grid-cols-5 gap-2">
                                @foreach ($slots as $slot)
                                    @php
                                        $slotIndex = $loop->iteration;
                                        $apt = $this->appointmentsByDoctorAndSlot[$doc->id][$slot] ?? null;
                                        $walkIn = $this->walkInTokensByDoctorAndSlot[$doc->id][$slot] ?? null;
                                    @endphp
                                    <div wire:key="cell-{{ $doc->id }}-{{ $slot }}" class="min-w-0">
                                        @if ($apt)
                                            @php($qt = $apt->queueToken)
                                            <div class="{{ $this->cellClasses($apt) }} flex min-h-[7.5rem] flex-col rounded-lg p-2.5 shadow-sm">
                                                <div class="flex items-start justify-between gap-1">
                                                    <span class="font-mono text-2xl font-black tabular-nums leading-none tracking-tight text-current">
                                                        {{ $qt ? 'T-'.$qt->token_number : '—' }}
                                                    </span>
                                                    <span class="shrink-0 font-mono text-[0.65rem] font-semibold tabular-nums text-current/70" title="{{ __("Slot :n in today's grid", ['n' => $slotIndex]) }}">#{{ $slotIndex }}</span>
                                                </div>
                                                <div class="mt-1 text-xs font-semibold tabular-nums opacity-90">
                                                    {{ $this->appointmentTime12h($apt->appointment_time) }}
                                                </div>
                                                <div class="mt-1 text-sm font-semibold leading-snug">
                                                    {{ $apt->patient?->name }}
                                                </div>
                                                @if ($apt->status === \App\Enums\AppointmentStatus::Booked)
                                                    <div class="mt-auto flex flex-col gap-1.5 pt-2">
                                                        <flux:button
                                                            size="xs"
                                                            variant="primary"
                                                            class="w-full"
                                                            wire:click="markArrived({{ $apt->id }})"
                                                            wire:loading.attr="disabled"
                                                        >
                                                            {{ __('Mark as arrived') }}
                                                        </flux:button>
                                                        <flux:button
                                                            size="xs"
                                                            variant="ghost"
                                                            class="w-full"
                                                            wire:click="cancelBooking({{ $apt->id }})"
                                                            wire:confirm="{{ __('Cancel this booking and release the token?') }}"
                                                        >
                                                            {{ __('Cancel') }}
                                                        </flux:button>
                                                    </div>
                                                @elseif ($apt->status === \App\Enums\AppointmentStatus::Arrived)
                                                    <div class="mt-auto pt-2 text-sm font-bold text-sky-900 dark:text-sky-100">
                                                        {{ __('Arrived ✓') }}
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif ($walkIn)
                                            <div class="{{ $this->walkInCellClasses() }} flex min-h-[7.5rem] flex-col rounded-lg p-2.5 shadow-sm">
                                                <div class="flex items-start justify-between gap-1">
                                                    <span class="font-mono text-2xl font-black tabular-nums leading-none text-zinc-900 dark:text-zinc-50">
                                                        T-{{ $walkIn->token_number }}
                                                    </span>
                                                    <span class="shrink-0 font-mono text-[0.65rem] font-semibold tabular-nums text-zinc-600 dark:text-zinc-300" title="{{ __("Slot :n in today's grid", ['n' => $slotIndex]) }}">#{{ $slotIndex }}</span>
                                                </div>
                                                <div class="mt-1 text-xs font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">
                                                    {{ $this->slotHumanTime($slot) }}
                                                </div>
                                                <div class="mt-1 text-sm font-semibold leading-snug underline decoration-zinc-400/80 underline-offset-2">
                                                    {{ $walkIn->patient?->name ?? __('Walk-in') }}
                                                </div>
                                                <div class="mt-auto pt-1 text-[0.65rem] font-medium uppercase tracking-wide text-zinc-600 dark:text-zinc-400">
                                                    {{ __('Used by walk-in') }}
                                                </div>
                                            </div>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="openBookSlot({{ $doc->id }}, @js($slot))"
                                                @disabled(! $this->activeShift)
                                                title="{{ __("Slot :n — book to get the next queue token (T-…)", ['n' => $slotIndex]) }}"
                                                class="relative flex min-h-[7.5rem] w-full flex-col justify-between rounded-lg border border-emerald-200 bg-emerald-50/95 p-2.5 text-start shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:hover:bg-emerald-950/45"
                                            >
                                                <span class="flex w-full items-start justify-between gap-1">
                                                    <span class="text-[0.65rem] font-semibold uppercase tracking-wide text-emerald-800/90 dark:text-emerald-200/90">{{ __('Available') }}</span>
                                                    <span class="font-mono text-sm font-black tabular-nums leading-none text-emerald-900/90 dark:text-emerald-100/95">#{{ $slotIndex }}</span>
                                                </span>
                                                <span class="font-mono text-2xl font-black tabular-nums text-emerald-950 dark:text-emerald-100">{{ $this->slotHumanTime($slot) }}</span>
                                                <span class="text-center text-xs font-bold text-emerald-900 dark:text-emerald-200">{{ __('Book') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if ($this->walkInTokensForDoctorToday->isNotEmpty())
                            <div class="mt-4 rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <flux:heading size="sm" class="text-zinc-900 dark:text-white">{{ __('Queue tokens (walk-in, no appointment)') }}</flux:heading>
                                </div>
                                <flux:text class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ __('Same tokens as the queue desk. On the grid, walk-in token T-n appears in slot #n (token number, not issue time). Tokens above :max appear only here.', ['max' => $slotCount]) }}
                                </flux:text>
                                <div class="mt-3 overflow-x-auto">
                                    <table class="w-full min-w-[32rem] text-left text-sm text-zinc-800 dark:text-zinc-200">
                                        <thead>
                                            <tr class="border-b border-zinc-200 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                                                <th class="py-2 pe-4">{{ __('Token') }}</th>
                                                <th class="py-2 pe-4">{{ __('Patient') }}</th>
                                                <th class="py-2 pe-4">{{ __('Issued') }}</th>
                                                <th class="py-2 pe-4">{{ __('On slot grid') }}</th>
                                                <th class="py-2">{{ __('Status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->walkInTokensForDoctorToday as $qt)
                                                <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="walk-in-row-{{ $doc->id }}-{{ $qt->id }}">
                                                    <td class="py-2 pe-4 font-mono font-semibold">T-{{ $qt->token_number }}</td>
                                                    <td class="py-2 pe-4">{{ $qt->patient?->name ?? '—' }}</td>
                                                    <td class="py-2 pe-4 tabular-nums">{{ $this->walkInTime12h($qt->created_at) }}</td>
                                                    <td class="py-2 pe-4">{{ $this->walkInTokenMapsToDoctorGrid($doc, $qt) ? __('Yes') : __('No') }}</td>
                                                    <td class="py-2 capitalize">{{ $this->queueTokenStatusLabel($qt) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- Book modal --}}
    <flux:modal wire:model="showBookModal" name="book-appointment" class="max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Book slot') }}</flux:heading>
            @if ($bookDoctorId && $bookSlotTime)
                @php($docName = $this->calendarDoctors->firstWhere('id', $bookDoctorId)?->name)
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $docName }} · {{ $this->slotHumanTime($bookSlotTime) }} · {{ now()->translatedFormat('l, M j') }}
                </flux:text>
            @endif

            <flux:callout color="zinc" icon="information-circle" class="text-sm">
                {{ __('Consultation only (service #:id). This booking uses the consultation queue and price for the selected doctor.', ['id' => 1]) }}
            </flux:callout>

            <flux:error name="book" />

            <form wire:submit="lookupPhone" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @php($phoneDigitCount = strlen(preg_replace('/\D/', '', $phoneQuery)))
                <flux:field class="min-w-0 flex-1" :description:trailing="__(':current / :need digits', ['current' => $phoneDigitCount, 'need' => 11])">
                    <flux:label>{{ __('Family phone') }}</flux:label>
                    <flux:input
                        wire:key="appt-phone-{{ $phoneFieldVersion }}"
                        wire:model.live="phoneQuery"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel"
                        placeholder="03001234567"
                        icon="device-phone-mobile"
                        :invalid="$phoneDigitCount > 0 && $phoneDigitCount !== 11"
                    />
                    <flux:error name="phoneQuery" />
                </flux:field>
                <flux:button type="submit" variant="primary" icon="magnifying-glass">{{ __('Search') }}</flux:button>
            </form>

            @if ($this->family)
                <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <div class="flex items-center justify-between gap-2">
                        <flux:text class="text-sm text-zinc-500">{{ __('Family') }} · {{ $this->family->phone }}</flux:text>
                        <flux:button size="sm" variant="ghost" wire:click="clearFamily">{{ __('Clear') }}</flux:button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($this->family->patients as $p)
                            <button
                                type="button"
                                wire:key="book-patient-{{ $p->id }}"
                                wire:click="selectPatient({{ $p->id }})"
                                @class([
                                    'rounded-xl border px-3 py-2.5 text-start text-sm transition',
                                    'border-violet-500 bg-violet-50 ring-2 ring-violet-500/25 dark:border-violet-400 dark:bg-violet-950/40' => $selectedPatientId === $p->id,
                                    'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900' => $selectedPatientId !== $p->id,
                                ])
                            >
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $p->name }}</span>
                            </button>
                        @endforeach
                    </div>
                    <flux:button variant="outline" size="sm" icon="user-plus" wire:click="openNewMemberModal">
                        {{ __('New person in this family') }}
                    </flux:button>
                </div>
            @endif

            <flux:field>
                <flux:label>{{ __('Notes (optional)') }}</flux:label>
                <flux:textarea wire:model="bookNotes" rows="2" placeholder="{{ __('e.g. Follow-up, fasting, interpreter…') }}" />
            </flux:field>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="$set('showBookModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" icon="calendar-days" wire:click="confirmBook" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirmBook">{{ __('Confirm booking') }}</span>
                    <span wire:loading wire:target="confirmBook">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showNewFamilyModal" name="appt-new-family" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('New family') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('Phone') }}: <span class="font-medium text-zinc-900 dark:text-white">{{ $phoneQuery }}</span>
            </flux:text>
            <flux:field>
                <flux:label>{{ __('Head of family — full name') }}</flux:label>
                <flux:input wire:model="newHeadName" />
                <flux:error name="newHeadName" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Gender') }}</flux:label>
                <flux:select wire:model="newHeadGender">
                    <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                    <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                </flux:select>
            </flux:field>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="$set('showNewFamilyModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="registerNewFamily" wire:loading.attr="disabled">{{ __('Create family') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showNewMemberModal" name="appt-new-member" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('New family member') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Full name') }}</flux:label>
                <flux:input wire:model="newMemberName" />
                <flux:error name="newMemberName" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Gender') }}</flux:label>
                <flux:select wire:model="newMemberGender">
                    <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                    <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                </flux:select>
            </flux:field>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="$set('showNewMemberModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="addFamilyMember" wire:loading.attr="disabled">{{ __('Save member') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showBroadcastModal" name="appt-broadcast" class="max-w-md">
        <form wire:submit="sendBroadcast" class="space-y-4">
            <flux:heading size="lg">{{ __('Broadcast to booked patients') }}</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Sends an SMS (VeevoTech) to every unique family phone with a booked appointment today (e.g. doctor running late).') }}
            </flux:text>
            <flux:field>
                <flux:label>{{ __('Message') }}</flux:label>
                <flux:textarea wire:model="broadcastBody" rows="4" required />
                <flux:error name="broadcastBody" />
            </flux:field>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showBroadcastModal', false)">{{ __('Close') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled">
                    {{ __('Log broadcast') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
