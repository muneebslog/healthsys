<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProcedureStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Procedure;
use App\Models\Shift;
use App\Services\ProcedurePaymentRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Procedure detail')] class extends Component
{
    public Procedure $procedure;

    public string $packagePriceInput = '';

    public string $paymentAmount = '';

    public string $paymentNote = '';

    public bool $showPackagePriceModal = false;

    public string $caseStatus = '';

    public string $admissionInput = '';

    public string $dischargeInput = '';

    public function mount(Procedure $procedure): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
            abort(403);
        }

        Gate::authorize('update', $procedure);

        $this->procedure = $procedure->load([
            'patient.family',
            'doctor:id,name,specialization',
            'invoices' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $this->packagePriceInput = (string) $this->procedure->package_price;
        $this->syncCaseFormFromProcedure();
    }

    public function openPackagePriceModal(): void
    {
        $this->packagePriceInput = (string) $this->procedure->package_price;
        $this->resetValidation('packagePriceInput');
        $this->showPackagePriceModal = true;
    }

    protected function syncCaseFormFromProcedure(): void
    {
        $this->caseStatus = $this->procedure->status->value;
        $tz = config('app.timezone');
        $this->admissionInput = $this->procedure->admission_at
            ? $this->procedure->admission_at->timezone($tz)->format('Y-m-d\TH:i')
            : '';
        $this->dischargeInput = $this->procedure->discharge_at
            ? $this->procedure->discharge_at->timezone($tz)->format('Y-m-d\TH:i')
            : '';
    }

    public function saveCaseProgress(): void
    {
        Gate::authorize('update', $this->procedure);

        $validated = $this->validate([
            'caseStatus' => ['required', Rule::enum(ProcedureStatus::class)],
            'admissionInput' => ['nullable', 'date'],
            'dischargeInput' => ['nullable', 'date'],
        ], [], [
            'caseStatus' => __('status'),
            'admissionInput' => __('admission'),
            'dischargeInput' => __('discharge'),
        ]);

        $tz = config('app.timezone');
        $admission = filled($validated['admissionInput'])
            ? Carbon::parse($validated['admissionInput'], $tz)
            : null;
        $discharge = filled($validated['dischargeInput'])
            ? Carbon::parse($validated['dischargeInput'], $tz)
            : null;

        if ($admission && $discharge && $discharge->lt($admission)) {
            $this->addError('dischargeInput', __('Discharge must be on or after admission.'));

            return;
        }

        $this->procedure->update([
            'status' => ProcedureStatus::from($validated['caseStatus']),
            'admission_at' => $admission,
            'discharge_at' => $discharge,
        ]);

        $this->procedure->refresh();
        $this->syncCaseFormFromProcedure();
    }

    #[Computed]
    public function activeShift(): ?Shift
    {
        return Shift::query()
            ->where('status', ShiftStatus::Open)
            ->first();
    }

    #[Computed]
    public function totalPaid(): int
    {
        return $this->procedure->totalPaidAmount();
    }

    #[Computed]
    public function balance(): int
    {
        return $this->procedure->balanceAmount();
    }

    #[Computed]
    public function isOverpaid(): bool
    {
        return $this->totalPaid > (int) $this->procedure->package_price;
    }

    public function savePackagePrice(): void
    {
        Gate::authorize('update', $this->procedure);

        $validated = $this->validate([
            'packagePriceInput' => ['required', 'integer', 'min:1'],
        ], [], [
            'packagePriceInput' => __('package price'),
        ]);

        $this->procedure->update([
            'package_price' => (int) $validated['packagePriceInput'],
        ]);

        $this->procedure->refresh();
        $this->packagePriceInput = (string) $this->procedure->package_price;
        $this->showPackagePriceModal = false;
        unset($this->totalPaid, $this->balance, $this->isOverpaid);
    }

    public function recordPayment(): void
    {
        Gate::authorize('update', $this->procedure);

        $shift = $this->activeShift;

        if (! $shift) {
            $this->addError('paymentAmount', __('Open a shift before recording a payment.'));

            return;
        }

        $validated = $this->validate([
            'paymentAmount' => ['required', 'integer', 'min:1'],
            'paymentNote' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'paymentAmount' => __('amount'),
        ]);

        $invoice = app(ProcedurePaymentRecorder::class)->record(
            $this->procedure,
            $shift,
            (int) $validated['paymentAmount'],
            $validated['paymentNote'] ?? null,
        );

        $this->procedure->refresh()->load([
            'patient.family',
            'doctor:id,name,specialization',
            'invoices' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $this->paymentAmount = '';
        $this->paymentNote = '';
        unset($this->totalPaid, $this->balance, $this->isOverpaid);

        $printUrl = route('invoices.print', ['invoice' => $invoice->id], absolute: true);
        $this->js('setTimeout(function(){ window.open('.Js::from($printUrl).', "_blank", "noopener,noreferrer"); }, 100)');
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

@php($p = $this->procedure)
<div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:button :href="route('reception.procedures')" variant="ghost" icon="arrow-left" wire:navigate class="w-fit">
            {{ __('Back to procedures') }}
        </flux:button>
    </div>

    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-slate-50 via-white to-cyan-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-cyan-950/20">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-cyan-400/15 blur-3xl dark:bg-cyan-500/10"></div>
        <div class="relative flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <flux:badge color="zinc">{{ $p->reference_number }}</flux:badge>
                <flux:badge color="cyan">{{ \Illuminate\Support\Str::headline($p->status->value) }}</flux:badge>
            </div>
            <flux:heading size="xl" class="text-zinc-900 dark:text-white">{{ $p->operation_name }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ $p->patient?->name ?? '—' }} · {{ $p->doctor?->name ?? '—' }}
                @if (filled($p->room_number))
                    · {{ __('Room') }} {{ $p->room_number }}
                @endif
            </flux:text>
        </div>
    </header>

    @if ($this->isOverpaid)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('Paid amount exceeds package price') }}">
            {{ __('The package price is lower than total payments recorded. Adjust the package price or document the difference outside the system if intentional.') }}
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="p-6 lg:col-span-3">
            <flux:heading size="md" class="mb-4">{{ __('Financial summary') }}</flux:heading>
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <dl class="min-w-0 flex-1 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Package price') }}</dt>
                        <dd class="tabular-nums font-semibold text-zinc-900 dark:text-white">{{ $this->formatMoney((int) $p->package_price) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Total paid') }}</dt>
                        <dd class="tabular-nums font-medium text-teal-700 dark:text-teal-300">{{ $this->formatMoney($this->totalPaid) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <dt class="text-zinc-500">{{ __('Balance') }}</dt>
                        <dd class="tabular-nums font-bold text-amber-800 dark:text-amber-200">{{ $this->formatMoney($this->balance) }}</dd>
                    </div>
                </dl>
                <flux:button variant="outline" icon="pencil-square" wire:click="openPackagePriceModal" class="w-full shrink-0 lg:w-auto">
                    {{ __('Change package price') }}
                </flux:button>
            </div>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4 p-6">
            <flux:heading size="md">{{ __('Record payment') }}</flux:heading>
            @if ($this->activeShift)
                <flux:badge color="lime" size="sm">{{ __('Shift open') }}</flux:badge>
            @else
                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('Open a shift on the Shift page to record payments.') }}
                </flux:callout>
            @endif
            <flux:field>
                <flux:label>{{ __('Amount') }}</flux:label>
                <flux:input type="number" wire:model="paymentAmount" min="1" :disabled="! $this->activeShift" />
                <flux:error name="paymentAmount" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Note') }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></flux:label>
                <flux:input wire:model="paymentNote" :disabled="! $this->activeShift" />
            </flux:field>
            <flux:button variant="primary" icon="printer" wire:click="recordPayment" wire:loading.attr="disabled" :disabled="! $this->activeShift">
                <span wire:loading.remove wire:target="recordPayment">{{ __('Save & print receipt') }}</span>
                <span wire:loading wire:target="recordPayment">{{ __('Saving…') }}</span>
            </flux:button>
        </flux:card>

        <flux:card class="space-y-4 p-6">
            <flux:heading size="md">{{ __('Case details') }}</flux:heading>
            @if ($p->procedure_date)
                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Procedure date') }}</dt>
                        <dd class="text-zinc-900 dark:text-white">{{ $p->procedure_date->format('M j, Y') }}</dd>
                    </div>
                </dl>
            @endif

            <div @class(['space-y-4', 'border-t border-zinc-100 pt-4 dark:border-zinc-800' => $p->procedure_date])>
                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model="caseStatus">
                        @foreach (\App\Enums\ProcedureStatus::cases() as $status)
                            <flux:select.option value="{{ $status->value }}">{{ \Illuminate\Support\Str::headline($status->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="caseStatus" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Admission date & time') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="admissionInput" />
                    <flux:description>{{ __('Leave empty until the patient is admitted.') }}</flux:description>
                    <flux:error name="admissionInput" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Discharge date & time') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="dischargeInput" />
                    <flux:description>{{ __('Leave empty until discharged.') }}</flux:description>
                    <flux:error name="dischargeInput" />
                </flux:field>
                <flux:button variant="primary" wire:click="saveCaseProgress" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveCaseProgress">{{ __('Save case progress') }}</span>
                    <span wire:loading wire:target="saveCaseProgress">{{ __('Saving…') }}</span>
                </flux:button>
            </div>

            @if (filled($p->notes))
                <div class="rounded-lg border border-zinc-200 bg-zinc-50/80 p-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-300">
                    {{ $p->notes }}
                </div>
            @endif
        </flux:card>
    </div>

    <flux:card class="p-6">
        <flux:heading size="md" class="mb-4">{{ __('Payment invoices') }}</flux:heading>
        @if ($p->invoices->isEmpty())
            <flux:text class="text-zinc-500">{{ __('No payments recorded yet.') }}</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="border-b border-zinc-100 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800">
                        <tr>
                            <th class="px-3 py-2">{{ __('Invoice') }}</th>
                            <th class="px-3 py-2">{{ __('When') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Amount') }}</th>
                            <th class="px-3 py-2">{{ __('Note') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($p->invoices as $inv)
                            <tr wire:key="inv-{{ $inv->id }}">
                                <td class="px-3 py-2 font-mono text-xs text-zinc-600">#{{ $inv->id }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $inv->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                                <td class="px-3 py-2 text-end tabular-nums font-medium">{{ $this->formatMoney((int) $inv->final_amount) }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ $inv->payment_note ?: '—' }}</td>
                                <td class="px-3 py-2 text-end">
                                    @if ($inv->status === InvoiceStatus::Paid)
                                        <flux:button size="sm" variant="ghost" :href="route('invoices.print', $inv)" target="_blank">
                                            {{ __('Print') }}
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>

    <flux:modal wire:model="showPackagePriceModal" name="procedure-package-price" class="min-w-[20rem] max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Change package price') }}</flux:heading>
            <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('Confirm before saving') }}">
                {{ __('Changing the package price updates balances immediately. Lowering it after payments can make the case look overpaid—only continue if this is intentional.') }}
            </flux:callout>
            <flux:field>
                <flux:label>{{ __('Package price') }}</flux:label>
                <flux:input type="number" wire:model="packagePriceInput" min="1" />
                <flux:error name="packagePriceInput" />
            </flux:field>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="$set('showPackagePriceModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="savePackagePrice" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="savePackagePrice">{{ __('Save package price') }}</span>
                    <span wire:loading wire:target="savePackagePrice">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
