<?php

use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Procedure;
use App\Models\Shift;
use App\Services\ProcedurePaymentRecorder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Procedure detail')] class extends Component
{
    public Procedure $procedure;

    public string $packagePriceInput = '';

    public string $paymentAmount = '';

    public string $paymentNote = '';

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
                <flux:badge color="cyan">{{ $p->status->value }}</flux:badge>
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
        <flux:card class="p-6 lg:col-span-1">
            <flux:heading size="md" class="mb-4">{{ __('Financial summary') }}</flux:heading>
            <dl class="space-y-3 text-sm">
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
        </flux:card>

        <flux:card class="space-y-4 p-6 lg:col-span-2">
            <flux:heading size="md">{{ __('Edit package price') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('Change the agreed package anytime; balances update automatically.') }}</flux:text>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:field class="sm:flex-1">
                    <flux:label>{{ __('Package price') }}</flux:label>
                    <flux:input type="number" wire:model="packagePriceInput" min="1" />
                    <flux:error name="packagePriceInput" />
                </flux:field>
                <flux:button variant="primary" wire:click="savePackagePrice" wire:loading.attr="disabled">
                    {{ __('Save') }}
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
            <dl class="grid gap-2 text-sm">
                @if ($p->procedure_date)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Procedure date') }}</dt>
                        <dd class="text-zinc-900 dark:text-white">{{ $p->procedure_date->format('M j, Y') }}</dd>
                    </div>
                @endif
                @if ($p->admission_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Admission') }}</dt>
                        <dd class="text-zinc-900 dark:text-white">{{ $p->admission_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if ($p->discharge_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Discharge') }}</dt>
                        <dd class="text-zinc-900 dark:text-white">{{ $p->discharge_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if (filled($p->notes))
                    <div class="mt-2 rounded-lg border border-zinc-200 bg-zinc-50/80 p-3 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-300">
                        {{ $p->notes }}
                    </div>
                @endif
            </dl>
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
</div>
