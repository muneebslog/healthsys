<?php

use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\QueueStatus;
use App\Enums\QueueTokenStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Walk-in')] class extends Component
{
    private const int PHONE_DIGITS = 11;

    public string $phoneQuery = '';

    public bool $quickMode = false;

    public string $quickName = '';

    /** Bumped on clear so the phone input remounts and cannot keep stale DOM text. */
    public int $phoneFieldVersion = 0;

    public ?int $familyId = null;

    public ?int $selectedPatientId = null;

    public ?int $pendingServiceId = null;

    public ?int $pendingDoctorId = null;

    /** @var array<int, array{queue_token_id: int, service_id: int, service_price_id: int, doctor_id: int|null, token_number: int, label: string, price: int}> */
    public array $lineItems = [];

    public bool $showNewFamilyModal = false;

    public bool $showNewMemberModal = false;

    public string $newHeadName = '';

    public string $newHeadGender = 'male';

    public string $newMemberName = '';

    public string $newMemberGender = 'male';

    public bool $showEditLinePriceModal = false;

    public ?int $editingLineIndex = null;

    public string $editLinePrice = '';

    public function mount(): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
            abort(403);
        }
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

    #[Computed]
    public function services()
    {
        $query = Service::query()
            ->where('is_active', true);

        if ($this->quickMode) {
            $query->where('allow_walkin_without_phone', true);
        }

        return $query
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function doctorsForPendingService()
    {
        if (! $this->pendingServiceId) {
            return collect();
        }

        $service = Service::query()->find($this->pendingServiceId);

        if (! $service || $service->is_standalone) {
            return collect();
        }

        return Doctor::query()
            ->where('status', 'active')
            ->whereHas(
                'servicePrices',
                fn ($q) => $q->where('service_id', $service->id)->where('is_active', true)
            )
            ->orderBy('name')
            ->get();
    }

    public function updatedPendingServiceId(mixed $value): void
    {
        if ($value === '' || $value === null) {
            $this->pendingServiceId = null;
        }

        $this->pendingDoctorId = null;
    }

    public function updatedPendingDoctorId(mixed $value): void
    {
        if ($value === '' || $value === null) {
            $this->pendingDoctorId = null;
        }
    }

    public function lookupPhone(): void
    {
        if ($this->quickMode) {
            return;
        }

        $this->validate($this->phoneQueryRules(), [], [
            'phoneQuery' => __('phone'),
        ]);

        $phone = $this->normalizePhone($this->phoneQuery);

        $family = Family::query()->where('phone', $phone)->first();

        $this->familyId = $family?->id;
        $this->selectedPatientId = null;
        $this->resetLineItemsQuiet();

        if (! $family) {
            $this->showNewFamilyModal = true;
        }
    }

    public function clearFamily(): void
    {
        $this->familyId = null;
        $this->selectedPatientId = null;
        $this->phoneQuery = '';
        $this->quickName = '';
        $this->phoneFieldVersion++;
        $this->resetLineItemsQuiet();
        $this->resetErrorBag();
    }

    public function switchToQuickMode(): void
    {
        $this->quickMode = true;
        $this->clearFamily();
    }

    public function switchToPhoneMode(): void
    {
        $this->quickMode = false;
        $this->clearFamily();
    }

    public function createQuickWalkIn(): void
    {
        if (! $this->activeShift) {
            $this->addError('quickName', __('Open a shift before registering walk-ins.'));

            return;
        }

        $validated = $this->validate([
            'quickName' => ['required', 'string', 'max:255'],
        ], [], [
            'quickName' => __('name'),
        ]);

        $family = DB::transaction(function () use ($validated) {
            $family = Family::query()->create(['phone' => null]);

            $head = Patient::query()->create([
                'family_id' => $family->id,
                'name' => $validated['quickName'],
                'gender' => 'other',
                'type' => PatientType::Head,
                'relation_to_head' => null,
            ]);

            $family->update(['head_id' => $head->id]);

            return $family;
        });

        $this->familyId = $family->id;
        $this->selectedPatientId = $family->head_id;
        $this->resetLineItemsQuiet();
        unset($this->family);
        $this->resetErrorBag();
    }

    public function selectPatient(int $patientId): void
    {
        if (! $this->family || ! $this->family->patients->contains('id', $patientId)) {
            return;
        }

        if ($this->selectedPatientId !== $patientId && count($this->lineItems) > 0) {
            $this->resetLineItemsQuiet();
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

    public function addLine(): void
    {
        $shift = $this->activeShift;

        if (! $shift) {
            $this->addError('line', __('Open a shift before adding services.'));

            return;
        }

        if (! $this->selectedPatientId) {
            $this->addError('line', __('Select a patient first.'));

            return;
        }

        $rules = [
            'pendingServiceId' => ['required', 'exists:services,id'],
        ];

        $service = Service::query()->findOrFail($this->pendingServiceId);

        if (! $service->is_standalone) {
            $rules['pendingDoctorId'] = ['required', 'exists:doctors,id'];
        }

        $this->validate($rules);

        $doctorId = $service->is_standalone ? null : $this->pendingDoctorId;

        $priceRow = $service->priceForDoctor($doctorId);

        if (! $priceRow || ! $priceRow->is_active) {
            $this->addError('line', __('No active price is configured for this service and doctor.'));

            return;
        }

        try {
            $row = DB::transaction(function () use ($shift, $service, $doctorId, $priceRow) {
                $queue = $this->findOrCreateActiveQueue($service->id, $doctorId, $shift->id);
                $tokenNumber = $queue->assignNextToken();

                $token = QueueToken::query()->create([
                    'queue_id' => $queue->id,
                    'patient_id' => $this->selectedPatientId,
                    'token_number' => $tokenNumber,
                    'status' => QueueTokenStatus::Waiting,
                ]);

                $doctorLabel = $doctorId
                    ? ($priceRow->doctor?->name ?? __('Doctor'))
                    : __('Standalone');

                $label = $service->name.' — '.$doctorLabel;

                return [
                    'queue_token_id' => $token->id,
                    'service_id' => $service->id,
                    'service_price_id' => $priceRow->id,
                    'doctor_id' => $priceRow->doctor_id,
                    'token_number' => $tokenNumber,
                    'label' => $label,
                    'price' => (int) $priceRow->price,
                ];
            });
        } catch (Throwable) {
            $this->addError('line', __('Could not assign a token. Try again.'));

            return;
        }

        $this->lineItems[] = $row;
        $this->pendingDoctorId = null;
        $this->resetErrorBag(['line', 'pendingServiceId', 'pendingDoctorId']);
    }

    public function removeLine(int $index): void
    {
        if (! isset($this->lineItems[$index])) {
            return;
        }

        $id = $this->lineItems[$index]['queue_token_id'];

        QueueToken::query()
            ->whereKey($id)
            ->whereNull('visit_id')
            ->delete();

        array_splice($this->lineItems, $index, 1);
        $this->lineItems = array_values($this->lineItems);

        if ($this->editingLineIndex === $index) {
            $this->showEditLinePriceModal = false;
            $this->editingLineIndex = null;
            $this->editLinePrice = '';
        } elseif ($this->editingLineIndex !== null && $this->editingLineIndex > $index) {
            $this->editingLineIndex--;
        }
    }

    public function openEditLinePrice(int $index): void
    {
        if (! isset($this->lineItems[$index])) {
            return;
        }

        $this->editingLineIndex = $index;
        $this->editLinePrice = (string) $this->lineItems[$index]['price'];
        $this->resetErrorBag(['editLinePrice']);
        $this->showEditLinePriceModal = true;
    }

    public function saveLinePrice(): void
    {
        if ($this->editingLineIndex === null || ! isset($this->lineItems[$this->editingLineIndex])) {
            $this->showEditLinePriceModal = false;
            $this->editingLineIndex = null;

            return;
        }

        $validated = $this->validate([
            'editLinePrice' => ['required', 'integer', 'min:1'],
        ], [], [
            'editLinePrice' => __('price'),
        ]);

        $this->lineItems[$this->editingLineIndex]['price'] = (int) $validated['editLinePrice'];
        $this->showEditLinePriceModal = false;
        $this->editingLineIndex = null;
        $this->editLinePrice = '';
    }

    public function updatedShowEditLinePriceModal(bool $value): void
    {
        if (! $value) {
            $this->editingLineIndex = null;
            $this->editLinePrice = '';
            $this->resetErrorBag(['editLinePrice']);
        }
    }

    public function createAndPrint(): void
    {
        $shift = $this->activeShift;

        if (! $shift) {
            $this->addError('checkout', __('Open a shift before creating an invoice.'));

            return;
        }

        $this->validate([
            'selectedPatientId' => ['required', 'exists:patients,id'],
            'lineItems' => ['required', 'array', 'min:1'],
        ], [], [
            'selectedPatientId' => __('patient'),
            'lineItems' => __('services'),
        ]);

        $invoiceId = null;

        try {
            DB::transaction(function () use ($shift, &$invoiceId) {
                $patient = Patient::query()
                    ->with('family')
                    ->lockForUpdate()
                    ->findOrFail($this->selectedPatientId);

                $tokenIds = collect($this->lineItems)->pluck('queue_token_id')->all();
                $tokens = QueueToken::query()->lockForUpdate()->whereIn('id', $tokenIds)->get()->keyBy('id');

                if ($tokens->count() !== count($tokenIds)) {
                    throw new RuntimeException('tokens_missing');
                }

                foreach ($tokenIds as $tid) {
                    $t = $tokens->get($tid);
                    if (! $t || $t->patient_id !== $patient->id || $t->visit_id !== null) {
                        throw new RuntimeException('token_invalid');
                    }
                }

                $doctorIds = collect($this->lineItems)
                    ->pluck('doctor_id')
                    ->filter()
                    ->unique()
                    ->values();

                $visitDoctorId = $doctorIds->count() === 1 ? (int) $doctorIds->first() : null;

                $visit = Visit::query()->create([
                    'patient_id' => $patient->id,
                    'family_id' => $patient->family_id,
                    'doctor_id' => $visitDoctorId,
                    'shift_id' => $shift->id,
                    'status' => VisitStatus::InProgress,
                ]);

                $total = 0;

                foreach ($this->lineItems as $row) {
                    $token = $tokens->get($row['queue_token_id']);
                    $sp = ServicePrice::query()->lockForUpdate()->findOrFail($row['service_price_id']);

                    VisitService::query()->create([
                        'visit_id' => $visit->id,
                        'service_id' => $row['service_id'],
                        'doctor_id' => $row['doctor_id'],
                        'service_price_id' => $sp->id,
                        'queue_token_id' => $token->id,
                        'status' => 'pending',
                    ]);

                    $token->update([
                        'visit_id' => $visit->id,
                        'paid_at' => now(),
                    ]);

                    $total += (int) $row['price'];
                }

                $invoice = Invoice::query()->create([
                    'visit_id' => $visit->id,
                    'patient_id' => $patient->id,
                    'shift_id' => $shift->id,
                    'total_amount' => $total,
                    'discount' => 0,
                    'final_amount' => $total,
                    'status' => InvoiceStatus::Paid,
                ]);

                $nextSlipIndexByDoctor = [];

                foreach ($this->lineItems as $row) {
                    $sp = ServicePrice::query()->with('doctor')->findOrFail($row['service_price_id']);
                    $charged = (int) $row['price'];
                    $doctorId = $row['doctor_id'] !== null ? (int) $row['doctor_id'] : null;
                    if ($sp->doctor_id && $doctorId !== null) {
                        if (! isset($nextSlipIndexByDoctor[$doctorId])) {
                            $nextSlipIndexByDoctor[$doctorId] = DoctorShareCalculator::countSlipsTodayForDoctor($doctorId);
                        }
                        $slipIndex = $nextSlipIndexByDoctor[$doctorId];
                        $nextSlipIndexByDoctor[$doctorId] = $slipIndex + 1;
                        $docShare = DoctorShareCalculator::amountForLine($sp, $charged, $slipIndex);
                    } else {
                        $docShare = 0;
                    }

                    InvoiceService::query()->create([
                        'invoice_id' => $invoice->id,
                        'service_id' => $row['service_id'],
                        'service_price_id' => $sp->id,
                        'doctor_id' => $row['doctor_id'],
                        'price' => $charged,
                        'doctor_share_amount' => $docShare,
                        'discount' => 0,
                        'final_amount' => $charged,
                    ]);
                }

                $invoiceId = $invoice->id;
            });
        } catch (RuntimeException $e) {
            if (in_array($e->getMessage(), ['tokens_missing', 'token_invalid'], true)) {
                $this->addError('checkout', __('This visit list is out of date. Clear lines and add services again.'));

                return;
            }
            throw $e;
        }

        if ($invoiceId === null) {
            return;
        }

        $this->reset(['lineItems', 'pendingServiceId', 'pendingDoctorId']);

        $printUrl = route('invoices.print', ['invoice' => $invoiceId], absolute: true);
        $this->js('setTimeout(function(){ window.open('.Js::from($printUrl).', "_blank", "noopener,noreferrer"); }, 100)');
    }

    protected function findOrCreateActiveQueue(int $serviceId, ?int $doctorId, int $shiftId): Queue
    {
        return Queue::findOrCreateActiveForShift($serviceId, $doctorId, $shiftId);
    }

    /**
     * @return array<string, list<string|Closure>>
     */
    protected function phoneQueryRules(): array
    {
        return [
            'phoneQuery' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
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

    protected function resetLineItemsQuiet(): void
    {
        foreach ($this->lineItems as $row) {
            QueueToken::query()
                ->whereKey($row['queue_token_id'])
                ->whereNull('visit_id')
                ->delete();
        }

        $this->lineItems = [];
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="walk-in-page mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-slate-50 via-white to-sky-50/50 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-sky-950/25">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-sky-400/15 blur-3xl dark:bg-sky-500/10"></div>
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Walk-in desk') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Look up a family by phone, choose who is visiting, stack services with tokens, then create the invoice — a receipt opens in a new tab to print.') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($this->activeShift)
                    <flux:badge color="lime">{{ __('Shift open') }}</flux:badge>
                @else
                    <flux:badge color="zinc">{{ __('No active shift') }}</flux:badge>
                @endif
            </div>
        </div>
    </header>

    <flux:error name="checkout" />

    @if (! $this->activeShift)
        <flux:callout color="amber" icon="exclamation-triangle">
            {{ __('Open a shift from the Shift page before registering walk-ins.') }}
        </flux:callout>
    @endif

    <div class="grid gap-8 lg:grid-cols-2">
        <flux:card class="space-y-6 p-6 sm:p-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading size="lg">{{ __('1. Patient') }}</flux:heading>
                <flux:button.group>
                    <flux:button size="sm" :variant="$quickMode ? 'primary' : 'outline'" type="button" wire:click="switchToQuickMode">
                        {{ __('Quick test (no phone)') }}
                    </flux:button>
                    <flux:button size="sm" :variant="$quickMode ? 'outline' : 'primary'" type="button" wire:click="switchToPhoneMode">
                        {{ __('Phone lookup') }}
                    </flux:button>
                </flux:button.group>
            </div>

            @if ($quickMode)
                <form wire:submit="createQuickWalkIn" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <flux:field class="min-w-0 flex-1">
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model.live="quickName" autocomplete="off" placeholder="{{ __('e.g. Ahmed, Ammi, Uncle') }}" icon="user" />
                        <flux:error name="quickName" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" icon="check" :disabled="! $this->activeShift" wire:loading.attr="disabled">
                        {{ __('Start') }}
                    </flux:button>
                </form>
            @else
                <form wire:submit="lookupPhone" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    @php($phoneDigitCount = strlen(preg_replace('/\D/', '', $phoneQuery)))
                    <flux:field
                        class="min-w-0 flex-1"
                        :description:trailing="__(':current / :need digits', ['current' => $phoneDigitCount, 'need' => 11])"
                    >
                        <flux:label>{{ __('Family phone') }}</flux:label>
                        <flux:input
                            wire:key="walkin-phone-{{ $phoneFieldVersion }}"
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
                    <flux:button type="submit" variant="primary" icon="magnifying-glass" :disabled="! $this->activeShift" wire:loading.attr="disabled">
                        {{ __('Search') }}
                    </flux:button>
                </form>
            @endif

            @if ($this->family)
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <flux:text class="text-sm text-zinc-500">
                            {{ __('Family') }}
                            @if ($this->family->phone)
                                · {{ $this->family->phone }}
                            @else
                                · {{ __('No phone') }}
                            @endif
                        </flux:text>
                        <flux:button size="sm" variant="ghost" wire:click="clearFamily">{{ __('Clear') }}</flux:button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($this->family->patients as $p)
                            <button
                                type="button"
                                wire:key="patient-{{ $p->id }}"
                                wire:click="selectPatient({{ $p->id }})"
                                @class([
                                    'rounded-xl border px-4 py-3 text-start transition',
                                    'border-sky-500 bg-sky-50 ring-2 ring-sky-500/30 dark:border-sky-400 dark:bg-sky-950/40' => $selectedPatientId === $p->id,
                                    'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900' => $selectedPatientId !== $p->id,
                                ])
                            >
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $p->name }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">
                                    {{ $p->type === \App\Enums\PatientType::Head ? __('Head') : __('Member') }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <flux:button variant="outline" size="sm" icon="user-plus" wire:click="openNewMemberModal">
                        {{ __('New person in this family') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <flux:card class="space-y-6 p-6 sm:p-8">
            <flux:heading size="lg">{{ __('2. Services & tokens') }}</flux:heading>
            <flux:error name="line" />
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Service') }}</flux:label>
                    <flux:select wire:model.live="pendingServiceId" placeholder="{{ __('Choose service') }}">
                        <flux:select.option value="">{{ __('Choose service') }}</flux:select.option>
                        @foreach ($this->services as $svc)
                            <flux:select.option value="{{ $svc->id }}">{{ $svc->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="pendingServiceId" />
                </flux:field>

                @if ($this->pendingServiceId)
                    @php($svc = $this->services->firstWhere('id', (int) $this->pendingServiceId))
                    @if ($svc && ! $svc->is_standalone)
                        <flux:field>
                            <flux:label>{{ __('Doctor') }}</flux:label>
                            <flux:select wire:model.live="pendingDoctorId" placeholder="{{ __('Choose doctor') }}">
                                <flux:select.option value="">{{ __('Choose doctor') }}</flux:select.option>
                                @foreach ($this->doctorsForPendingService as $doc)
                                    <flux:select.option value="{{ $doc->id }}">{{ $doc->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="pendingDoctorId" />
                        </flux:field>
                    @endif
                @endif

                <flux:button
                    variant="primary"
                    icon="plus"
                    type="button"
                    wire:click="addLine"
                    :disabled="! $this->activeShift || ! $this->selectedPatientId"
                    wire:loading.attr="disabled"
                >
                    {{ __('Add to visit') }}
                </flux:button>
            </div>

            @if (count($lineItems) > 0)
                @php($showTokenLinePrefix = count($lineItems) > 1)
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Token') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Service') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-300">{{ __('Price') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-300"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($lineItems as $idx => $line)
                                @php($linePrefix = $idx < 26 ? chr(65 + $idx) : 'S'.($idx + 1))
                                <tr wire:key="line-{{ $line['queue_token_id'] }}">
                                    <td class="px-4 py-3 font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                                        @if ($showTokenLinePrefix)
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ $linePrefix }}·</span>{{ $line['token_number'] }}
                                        @else
                                            {{ $line['token_number'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-zinc-800 dark:text-zinc-200">{{ $line['label'] }}</td>
                                    <td class="px-4 py-3 text-end tabular-nums text-zinc-700 dark:text-zinc-300">{{ $this->formatMoney($line['price']) }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <div class="flex flex-wrap items-center justify-end gap-1">
                                            <flux:button size="xs" variant="ghost" icon="pencil-square" type="button" wire:click="openEditLinePrice({{ $idx }})">{{ __('Edit') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" icon="trash" type="button" wire:click="removeLine({{ $idx }})">{{ __('Remove') }}</flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('Total') }}:
                        <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">
                            {{ $this->formatMoney(collect($lineItems)->sum('price')) }}
                        </span>
                    </flux:text>
                    <flux:button
                        variant="primary"
                        icon="printer"
                        type="button"
                        wire:click="createAndPrint"
                        :disabled="! $this->activeShift"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="createAndPrint">{{ __('Create & print') }}</span>
                        <span wire:loading wire:target="createAndPrint">{{ __('Saving…') }}</span>
                    </flux:button>
                </div>
            @endif
        </flux:card>
    </div>

    <flux:modal wire:model="showNewFamilyModal" name="new-family" class="max-w-md">
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

    <flux:modal wire:model="showEditLinePriceModal" name="edit-line-price" class="max-w-md">
        <form wire:submit="saveLinePrice" class="space-y-4">
            <flux:heading size="lg">{{ __('Edit line price') }}</flux:heading>
            @php($editIdx = is_int($editingLineIndex) ? $editingLineIndex : null)
            @if ($editIdx !== null && array_key_exists($editIdx, $lineItems))
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $lineItems[$editIdx]['label'] ?? '' }}
                </flux:text>
            @endif
            <flux:field>
                <flux:label>{{ __('Price') }}</flux:label>
                <flux:input type="number" wire:model="editLinePrice" min="1" step="1" placeholder="0" />
                <flux:error name="editLinePrice" />
            </flux:field>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showEditLinePriceModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveLinePrice">{{ __('Save') }}</span>
                    <span wire:loading wire:target="saveLinePrice">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showNewMemberModal" name="new-member" class="max-w-md">
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
</div>
