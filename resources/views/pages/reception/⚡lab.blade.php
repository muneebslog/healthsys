<?php

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\Visit;
use App\Services\LabInvoiceLineAllocator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lab checkout')] class extends Component
{
    private const int PHONE_DIGITS = 11;

    public string $phoneQuery = '';

    public bool $quickMode = false;

    public string $quickName = '';

    public int $phoneFieldVersion = 0;

    public ?int $familyId = null;

    public ?int $selectedPatientId = null;

    /** @var list<int> */
    public array $selectedTestIds = [];

    public string $testSearch = '';

    public ?int $pendingTestId = null;

    public int $discountPercent = 0;

    public bool $showNewFamilyModal = false;

    public bool $showNewMemberModal = false;

    public string $newHeadName = '';

    public string $newHeadGender = 'male';

    public string $newMemberName = '';

    public string $newMemberGender = 'male';

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
    public function labTestsCatalog()
    {
        $q = LabTest::query()->where('is_active', true)->orderBy('test_code');

        $term = trim($this->testSearch);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)->orWhere('test_code', 'like', $like);
            });
        }

        return $q->get();
    }

    /**
     * @return list<array{index: int, id: int, code: string, name: string, price: int}>
     */
    #[Computed]
    public function selectedTestsRows(): array
    {
        $rows = [];
        foreach ($this->selectedTestIds as $index => $id) {
            $t = LabTest::query()->find($id);
            if (! $t) {
                continue;
            }
            $rows[] = [
                'index' => $index,
                'id' => $t->id,
                'code' => $t->test_code,
                'name' => $t->name,
                'price' => (int) $t->price,
            ];
        }

        return $rows;
    }

    #[Computed]
    public function subtotalAmount(): int
    {
        return (int) collect($this->selectedTestsRows)->sum('price');
    }

    #[Computed]
    public function discountAmountPreview(): int
    {
        $p = max(0, min(100, $this->discountPercent));

        return (int) floor($this->subtotalAmount * $p / 100);
    }

    #[Computed]
    public function finalAmountPreview(): int
    {
        return $this->subtotalAmount - $this->discountAmountPreview;
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
        $this->clearTestsQuiet();

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
        $this->clearTestsQuiet();
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
        $this->clearTestsQuiet();
        unset($this->family);
        $this->resetErrorBag();
    }

    public function selectPatient(int $patientId): void
    {
        if (! $this->family || ! $this->family->patients->contains('id', $patientId)) {
            return;
        }

        if ($this->selectedPatientId !== $patientId && count($this->selectedTestIds) > 0) {
            $this->clearTestsQuiet();
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

    public function addPendingTest(): void
    {
        if (! $this->pendingTestId) {
            return;
        }

        $id = (int) $this->pendingTestId;
        $exists = LabTest::query()->whereKey($id)->where('is_active', true)->exists();

        if (! $exists) {
            $this->addError('pendingTestId', __('Choose a valid active test.'));

            return;
        }

        $this->selectedTestIds[] = $id;
        $this->pendingTestId = null;
        unset($this->selectedTestsRows, $this->subtotalAmount, $this->discountAmountPreview, $this->finalAmountPreview);
        $this->resetErrorBag(['pendingTestId']);
    }

    public function removeTestAt(int $index): void
    {
        if (! isset($this->selectedTestIds[$index])) {
            return;
        }

        array_splice($this->selectedTestIds, $index, 1);
        $this->selectedTestIds = array_values($this->selectedTestIds);
        unset($this->selectedTestsRows, $this->subtotalAmount, $this->discountAmountPreview, $this->finalAmountPreview);
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
            'selectedTestIds' => ['required', 'array', 'min:1'],
            'discountPercent' => ['required', 'integer', 'min:0', 'max:100'],
        ], [], [
            'selectedPatientId' => __('patient'),
            'selectedTestIds' => __('tests'),
            'discountPercent' => __('Discount %'),
        ]);

        $invoiceId = null;

        try {
            DB::transaction(function () use ($shift, &$invoiceId): void {
                $patient = Patient::query()
                    ->with('family')
                    ->lockForUpdate()
                    ->findOrFail($this->selectedPatientId);

                $orderedTests = [];
                foreach ($this->selectedTestIds as $tid) {
                    $test = LabTest::query()
                        ->whereKey((int) $tid)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $test) {
                        throw new \RuntimeException('tests_invalid');
                    }

                    $orderedTests[] = $test;
                }

                $lines = LabInvoiceLineAllocator::allocateLines($orderedTests, $this->discountPercent);
                $subtotal = (int) array_sum(array_column($lines, 'list_price'));
                $discountTotal = (int) floor($subtotal * max(0, min(100, $this->discountPercent)) / 100);
                $finalAmount = $subtotal - $discountTotal;

                $visit = Visit::query()->create([
                    'patient_id' => $patient->id,
                    'family_id' => $patient->family_id,
                    'doctor_id' => null,
                    'shift_id' => $shift->id,
                    'status' => VisitStatus::InProgress,
                ]);

                $invoice = Invoice::query()->create([
                    'visit_id' => $visit->id,
                    'patient_id' => $patient->id,
                    'shift_id' => $shift->id,
                    'kind' => InvoiceKind::Lab,
                    'total_amount' => $subtotal,
                    'discount' => $discountTotal,
                    'discount_percent' => max(0, min(100, $this->discountPercent)),
                    'final_amount' => $finalAmount,
                    'status' => InvoiceStatus::Paid,
                ]);

                foreach ($lines as $row) {
                    $invoice->labTests()->create($row);
                }

                $invoiceId = $invoice->id;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'tests_invalid') {
                $this->addError('checkout', __('One or more tests are no longer available. Refresh and try again.'));

                return;
            }

            throw $e;
        }

        if ($invoiceId === null) {
            return;
        }

        $this->reset(['selectedTestIds', 'pendingTestId', 'discountPercent', 'testSearch']);
        unset($this->selectedTestsRows, $this->subtotalAmount, $this->discountAmountPreview, $this->finalAmountPreview);

        $printUrl = route('invoices.print', ['invoice' => $invoiceId], absolute: true);
        $this->js('setTimeout(function(){ window.open('.Js::from($printUrl).', "_blank", "noopener,noreferrer"); }, 100)');
    }

    protected function clearTestsQuiet(): void
    {
        $this->selectedTestIds = [];
        $this->pendingTestId = null;
        $this->discountPercent = 0;
        unset($this->selectedTestsRows, $this->subtotalAmount, $this->discountAmountPreview, $this->finalAmountPreview);
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

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="lab-checkout-page mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-slate-50 via-white to-teal-50/50 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-teal-950/25">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-teal-400/15 blur-3xl dark:bg-teal-500/10"></div>
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Lab checkout') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Look up the patient, add lab tests, set a discount percentage on the subtotal, then create the invoice — the receipt opens in a new tab.') }}
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
            {{ __('Open a shift from the Shift page before lab checkout.') }}
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
                            wire:key="lab-phone-{{ $phoneFieldVersion }}"
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
                                wire:key="lab-patient-{{ $p->id }}"
                                wire:click="selectPatient({{ $p->id }})"
                                @class([
                                    'rounded-xl border px-4 py-3 text-start transition',
                                    'border-teal-500 bg-teal-50 ring-2 ring-teal-500/30 dark:border-teal-400 dark:bg-teal-950/40' => $selectedPatientId === $p->id,
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
            <flux:heading size="lg">{{ __('2. Tests & discount') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Search tests') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="testSearch" icon="magnifying-glass" placeholder="{{ __('Code or name…') }}" />
            </flux:field>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Add test') }}</flux:label>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <flux:select wire:model.live="pendingTestId" class="min-w-0 flex-1" placeholder="{{ __('Choose test') }}">
                            <flux:select.option value="">{{ __('Choose test') }}</flux:select.option>
                            @foreach ($this->labTestsCatalog as $t)
                                <flux:select.option value="{{ $t->id }}">{{ $t->test_code }} — {{ $t->name }} ({{ number_format($t->price) }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button type="button" variant="primary" icon="plus" wire:click="addPendingTest" :disabled="! $pendingTestId">
                            {{ __('Add') }}
                        </flux:button>
                    </div>
                    <flux:error name="pendingTestId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Discount on subtotal (%)') }}</flux:label>
                    <flux:input type="number" wire:model.number.live="discountPercent" min="0" max="100" />
                    <flux:error name="discountPercent" />
                    <flux:text class="text-sm text-zinc-500">{{ __('0–100. Full discount is allowed.') }}</flux:text>
                </flux:field>
            </div>

            @if (count($selectedTestIds) > 0)
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Code') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Test') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-300">{{ __('Price') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-300"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->selectedTestsRows as $row)
                                <tr wire:key="lab-line-{{ $row['index'] }}-{{ $row['id'] }}">
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $row['code'] }}</td>
                                    <td class="px-4 py-3 text-zinc-800 dark:text-zinc-200">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3 text-end tabular-nums text-zinc-700 dark:text-zinc-300">{{ $this->formatMoney($row['price']) }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <flux:button size="xs" variant="ghost" icon="trash" type="button" wire:click="removeTestAt({{ $row['index'] }})">{{ __('Remove') }}</flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                    <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400">
                        <span>{{ __('Subtotal') }}</span>
                        <span class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ $this->formatMoney($this->subtotalAmount) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400">
                        <span>{{ __('Discount') }} ({{ $discountPercent }}%)</span>
                        <span class="tabular-nums font-medium text-amber-700 dark:text-amber-400">−{{ $this->formatMoney($this->discountAmountPreview) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-zinc-200 pt-2 text-sm font-semibold text-zinc-900 dark:border-zinc-600 dark:text-white">
                        <span>{{ __('Total due') }}</span>
                        <span class="tabular-nums">{{ $this->formatMoney($this->finalAmountPreview) }}</span>
                    </div>
                </div>

                <flux:button
                    variant="primary"
                    icon="printer"
                    type="button"
                    wire:click="createAndPrint"
                    :disabled="! $this->activeShift || ! $selectedPatientId"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="createAndPrint">{{ __('Create & print') }}</span>
                    <span wire:loading wire:target="createAndPrint">{{ __('Saving…') }}</span>
                </flux:button>
            @endif
        </flux:card>
    </div>

    <flux:modal wire:model="showNewFamilyModal" name="lab-new-family" class="max-w-md">
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

    <flux:modal wire:model="showNewMemberModal" name="lab-new-member" class="max-w-md">
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
