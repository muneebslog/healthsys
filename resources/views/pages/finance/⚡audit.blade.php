<?php

use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Models\Invoice;
use App\Models\Shift;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Audit & reconciliation')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    protected function range(): array
    {
        $from = \Carbon\Carbon::parse($this->dateFrom)->startOfDay();
        $to = \Carbon\Carbon::parse($this->dateTo)->endOfDay();

        return [$from, $to];
    }

    #[Computed]
    public function closedShiftsCount(): int
    {
        [$from, $to] = $this->range();

        return Shift::query()
            ->where('status', ShiftStatus::Closed)
            ->whereBetween('closed_at', [$from, $to])
            ->count();
    }

    #[Computed]
    public function hasOpenShift(): bool
    {
        return Shift::query()->where('status', ShiftStatus::Open)->exists();
    }

    #[Computed]
    public function invoicesWithDiscountCount(): int
    {
        [$from, $to] = $this->range();

        return Invoice::query()
            ->where('discount', '>', 0)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    #[Computed]
    public function cancelledInvoicesCount(): int
    {
        [$from, $to] = $this->range();

        return Invoice::query()
            ->where('status', InvoiceStatus::Cancelled)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    #[Computed]
    public function discountInvoices()
    {
        [$from, $to] = $this->range();

        return Invoice::query()
            ->with(['patient'])
            ->where('discount', '>', 0)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('discount')
            ->limit(25)
            ->get();
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/20">
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Audit & reconciliation') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Period checklist: closed shifts, discounts, and cancelled invoices.') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <flux:field>
                    <flux:label>{{ __('From') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateFrom" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('To') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateTo" />
                </flux:field>
            </div>
        </div>
    </header>

    @if ($this->hasOpenShift)
        <flux:callout color="amber" icon="exclamation-triangle">
            {{ __('There is an open shift. Close it before treating the period as fully reconciled.') }}
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Shifts closed (period)') }}</flux:text>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->closedShiftsCount }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Invoices with discount') }}</flux:text>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->invoicesWithDiscountCount }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Cancelled invoices') }}</flux:text>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-rose-800 dark:text-rose-300">{{ $this->cancelledInvoicesCount }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="md" class="mb-4">{{ __('Largest discounts (sample)') }}</flux:heading>
        @if ($this->discountInvoices->isEmpty())
            <flux:text class="text-zinc-500">{{ __('No discounted invoices in this range.') }}</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="border-b border-zinc-100 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800">
                        <tr>
                            <th class="py-2 pe-4">{{ __('Invoice') }}</th>
                            <th class="py-2 pe-4">{{ __('Patient') }}</th>
                            <th class="py-2 text-end">{{ __('Discount') }}</th>
                            <th class="py-2 text-end">{{ __('Final') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->discountInvoices as $inv)
                            <tr wire:key="disc-{{ $inv->id }}">
                                <td class="py-3 tabular-nums text-zinc-600">#{{ $inv->id }}</td>
                                <td class="py-3 font-medium text-zinc-900 dark:text-white">{{ $inv->patient?->name ?? '—' }}</td>
                                <td class="py-3 text-end tabular-nums text-amber-800 dark:text-amber-300">{{ $this->formatMoney((int) $inv->discount) }}</td>
                                <td class="py-3 text-end tabular-nums text-zinc-700 dark:text-zinc-300">{{ $this->formatMoney((int) $inv->final_amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
