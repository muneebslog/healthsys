<?php

use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\ProcedureStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Family;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Shift;
use App\Services\ProcedurePaymentRecorder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Procedures')] class extends Component
{
    use WithPagination;

    private const int PHONE_DIGITS = 11;

    public string $search = '';

    public bool $showCreateModal = false;

    public string $phoneQuery = '';

    public ?int $familyId = null;

    public ?int $selectedPatientId = null;

    public string $newHeadName = '';

    public string $newHeadGender = 'male';

    public string $newMemberName = '';

    public string $newMemberGender = 'male';

    public bool $showNewFamilyModal = false;

    public bool $showNewMemberModal = false;

    public string $referenceNumber = '';

    public ?int $doctorId = null;

    public string $operationName = '';

    public string $packagePrice = '';

    public string $roomNumber = '';

    public string $procedureDate = '';

    public string $notes = '';

    public string $status = 'scheduled';

    public string $admissionAt = '';

    public string $dischargeAt = '';

    public bool $recordFirstPayment = false;

    public string $firstPaymentAmount = '';

    public string $firstPaymentNote = '';

    public function mount(): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
            abort(403);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Procedure::class);
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    protected function resetCreateForm(): void
    {
        $this->phoneQuery = '';
        $this->familyId = null;
        $this->selectedPatientId = null;
        $this->referenceNumber = '';
        $this->doctorId = null;
        $this->operationName = '';
        $this->packagePrice = '';
        $this->roomNumber = '';
        $this->procedureDate = '';
        $this->notes = '';
        $this->status = ProcedureStatus::Scheduled->value;
        $this->admissionAt = '';
        $this->dischargeAt = '';
        $this->recordFirstPayment = false;
        $this->firstPaymentAmount = '';
        $this->firstPaymentNote = '';
        $this->newHeadName = '';
        $this->newHeadGender = 'male';
        $this->newMemberName = '';
        $this->newMemberGender = 'male';
        unset($this->family);
        $this->resetErrorBag();
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
    public function activeDoctors()
    {
        return Doctor::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function procedures()
    {
        $term = trim($this->search);
        $like = $term !== '' ? '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%' : null;

        return Procedure::query()
            ->with(['patient:id,name', 'doctor:id,name'])
            ->withSum([
                'invoices' => fn ($q) => $q->where('status', InvoiceStatus::Paid),
            ], 'final_amount')
            ->when($like !== null, function ($q) use ($like): void {
                $q->where(function ($q) use ($like): void {
                    $q->where('reference_number', 'like', $like)
                        ->orWhere('operation_name', 'like', $like)
                        ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like));
                });
            })
            ->latest('id')
            ->paginate(12);
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

    public function selectPatient(int $patientId): void
    {
        if (! $this->family || ! $this->family->patients->contains('id', $patientId)) {
            return;
        }

        $this->selectedPatientId = $patientId;
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

            return $family;
        });

        $this->familyId = $family->id;
        $this->selectedPatientId = $family->head_id;
        $this->showNewFamilyModal = false;
        unset($this->family);
        $this->resetErrorBag();
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

    public function saveProcedure(): void
    {
        Gate::authorize('create', Procedure::class);

        $shift = $this->activeShift;

        if (! $shift) {
            $this->addError('packagePrice', __('Open a shift before creating a procedure.'));

            return;
        }

        $rules = [
            'referenceNumber' => ['required', 'string', 'max:128'],
            'selectedPatientId' => ['required', 'exists:patients,id'],
            'doctorId' => ['required', 'exists:doctors,id'],
            'operationName' => ['required', 'string', 'max:255'],
            'packagePrice' => ['required', 'integer', 'min:1'],
            'roomNumber' => ['nullable', 'string', 'max:64'],
            'procedureDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:scheduled,in_progress,completed,cancelled'],
            'admissionAt' => ['nullable', 'string', 'max:32'],
            'dischargeAt' => ['nullable', 'string', 'max:32'],
            'firstPaymentAmount' => ['nullable', 'integer', 'min:1'],
            'firstPaymentNote' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->recordFirstPayment) {
            $rules['firstPaymentAmount'] = ['required', 'integer', 'min:1'];
        }

        $validated = $this->validate($rules, [], [
            'referenceNumber' => __('reference number'),
            'selectedPatientId' => __('patient'),
            'doctorId' => __('doctor'),
            'operationName' => __('operation'),
            'packagePrice' => __('package price'),
        ]);

        $packagePrice = (int) $validated['packagePrice'];
        $firstAmount = $this->recordFirstPayment ? (int) $validated['firstPaymentAmount'] : null;

        $procedureId = null;
        $printInvoiceId = null;

        try {
            DB::transaction(function () use ($validated, $shift, $packagePrice, $firstAmount, &$procedureId, &$printInvoiceId): void {
                $procedure = Procedure::query()->create([
                    'reference_number' => $validated['referenceNumber'],
                    'patient_id' => (int) $validated['selectedPatientId'],
                    'doctor_id' => (int) $validated['doctorId'],
                    'operation_name' => $validated['operationName'],
                    'package_price' => $packagePrice,
                    'room_number' => $validated['roomNumber'] ?: null,
                    'procedure_date' => $validated['procedureDate'] ?: null,
                    'notes' => $validated['notes'] ?: null,
                    'status' => ProcedureStatus::from($validated['status']),
                    'admission_at' => filled($validated['admissionAt']) ? $validated['admissionAt'] : null,
                    'discharge_at' => filled($validated['dischargeAt']) ? $validated['dischargeAt'] : null,
                ]);

                $procedureId = $procedure->id;

                if ($firstAmount !== null) {
                    $invoice = app(ProcedurePaymentRecorder::class)->record(
                        $procedure,
                        $shift,
                        $firstAmount,
                        $validated['firstPaymentNote'] ?? null,
                    );
                    $printInvoiceId = $invoice->id;
                }
            });
        } catch (\Throwable) {
            $this->addError('referenceNumber', __('Could not save. Try again.'));

            return;
        }

        $this->showCreateModal = false;
        $this->resetCreateForm();
        $this->resetPage();

        if ($printInvoiceId !== null) {
            $printUrl = route('invoices.print', ['invoice' => $printInvoiceId], absolute: true);
            $this->js('setTimeout(function(){ window.open('.Js::from($printUrl).', "_blank", "noopener,noreferrer"); }, 100)');
        }

        if ($procedureId !== null) {
            $this->redirect(route('reception.procedures.show', ['procedure' => $procedureId]), navigate: true);
        }
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-slate-50 via-white to-cyan-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-cyan-950/20">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-cyan-400/15 blur-3xl dark:bg-cyan-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Procedures (OT)') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Register operations, set an agreed package price (editable later), and collect payments in one or more invoices.') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($this->activeShift)
                    <flux:badge color="lime">{{ __('Shift open') }}</flux:badge>
                @else
                    <flux:badge color="amber">{{ __('No open shift') }}</flux:badge>
                @endif
                <flux:button variant="primary" icon="plus" type="button" wire:click="openCreateModal">
                    {{ __('New procedure') }}
                </flux:button>
            </div>
        </div>
    </header>

    <flux:card class="p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search ref, patient, operation…')" class="max-w-md" />
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-zinc-100 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800">
                    <tr>
                        <th class="px-3 py-3">{{ __('Ref') }}</th>
                        <th class="px-3 py-3">{{ __('Patient') }}</th>
                        <th class="px-3 py-3">{{ __('Doctor') }}</th>
                        <th class="px-3 py-3">{{ __('Operation') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Package') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Paid') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Balance') }}</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->procedures as $p)
                        @php($paid = (int) ($p->invoices_sum_final_amount ?? 0))
                        <tr wire:key="proc-{{ $p->id }}" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                            <td class="px-3 py-3 font-medium text-zinc-900 dark:text-white">{{ $p->reference_number }}</td>
                            <td class="px-3 py-3 text-zinc-700 dark:text-zinc-300">{{ $p->patient?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-zinc-700 dark:text-zinc-300">{{ $p->doctor?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-zinc-700 dark:text-zinc-300">{{ $p->operation_name }}</td>
                            <td class="px-3 py-3 text-end tabular-nums">{{ $this->formatMoney((int) $p->package_price) }}</td>
                            <td class="px-3 py-3 text-end tabular-nums text-teal-700 dark:text-teal-300">{{ $this->formatMoney($paid) }}</td>
                            <td class="px-3 py-3 text-end tabular-nums font-medium {{ (int) $p->package_price - $paid !== 0 ? 'text-amber-800 dark:text-amber-200' : 'text-zinc-600' }}">
                                {{ $this->formatMoney((int) $p->package_price - $paid) }}
                            </td>
                            <td class="px-3 py-3 text-end">
                                <flux:button size="sm" variant="ghost" :href="route('reception.procedures.show', $p)" wire:navigate>
                                    {{ __('Open') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-12 text-center text-zinc-500">{{ __('No procedures yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->procedures->links() }}
        </div>
    </flux:card>

    <flux:modal wire:model="showCreateModal" name="proc-create" class="max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('New procedure') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Case / reference #') }}</flux:label>
                <flux:input wire:model="referenceNumber" />
                <flux:error name="referenceNumber" />
            </flux:field>

            <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                <flux:heading size="sm" class="mb-3">{{ __('Patient') }}</flux:heading>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:input wire:model="phoneQuery" :placeholder="__('11-digit phone')" class="sm:flex-1" />
                    <flux:button type="button" variant="filled" wire:click="lookupPhone">{{ __('Look up') }}</flux:button>
                </div>
                <flux:error name="phoneQuery" />
                @if ($this->family)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($this->family->patients as $pat)
                            <flux:button
                                size="sm"
                                type="button"
                                :variant="$selectedPatientId === $pat->id ? 'primary' : 'ghost'"
                                wire:click="selectPatient({{ $pat->id }})"
                            >
                                {{ $pat->name }}
                            </flux:button>
                        @endforeach
                        <flux:button size="sm" variant="ghost" wire:click="openNewMemberModal">{{ __('+ Member') }}</flux:button>
                    </div>
                @endif
                <flux:error name="selectedPatientId" />
            </div>

            <flux:field>
                <flux:label>{{ __('Doctor') }}</flux:label>
                <flux:select wire:model="doctorId" placeholder="{{ __('Select doctor') }}">
                    @foreach ($this->activeDoctors as $d)
                        <flux:select.option value="{{ $d->id }}">{{ $d->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="doctorId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Operation name') }}</flux:label>
                <flux:input wire:model="operationName" />
                <flux:error name="operationName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Package price') }}</flux:label>
                <flux:input type="number" wire:model="packagePrice" min="1" />
                <flux:error name="packagePrice" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Room #') }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></flux:label>
                <flux:input wire:model="roomNumber" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Procedure date') }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></flux:label>
                <flux:input type="date" wire:model="procedureDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model="status">
                    <flux:select.option value="scheduled">{{ __('Scheduled') }}</flux:select.option>
                    <flux:select.option value="in_progress">{{ __('In progress') }}</flux:select.option>
                    <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
                    <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></flux:label>
                <flux:textarea wire:model="notes" rows="2" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Admission') }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></flux:label>
                <flux:input type="datetime-local" wire:model="admissionAt" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Discharge') }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></flux:label>
                <flux:input type="datetime-local" wire:model="dischargeAt" />
            </flux:field>

            <flux:separator />

            <flux:checkbox wire:model="recordFirstPayment" :label="__('Record first payment now (advance)')" />

            @if ($recordFirstPayment)
                <flux:field>
                    <flux:label>{{ __('Payment amount') }}</flux:label>
                    <flux:input type="number" wire:model="firstPaymentAmount" min="1" />
                    <flux:error name="firstPaymentAmount" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Payment note') }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></flux:label>
                    <flux:input wire:model="firstPaymentNote" />
                </flux:field>
            @endif

            <flux:error name="packagePrice" />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="closeCreateModal">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveProcedure" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveProcedure">{{ __('Save procedure') }}</span>
                    <span wire:loading wire:target="saveProcedure">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showNewFamilyModal" name="proc-new-family" class="max-w-md">
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

    <flux:modal wire:model="showNewMemberModal" name="proc-new-member" class="max-w-md">
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
