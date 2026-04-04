<?php

use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Models\DoctorShareLedger;
use App\Models\Invoice;
use App\Models\Shift;
use App\Models\ShiftExpense;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Finance dashboard')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    protected function range(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        return [$from, $to];
    }

    #[Computed]
    public function revenueTotal(): int
    {
        [$from, $to] = $this->range();

        return (int) Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->whereBetween('created_at', [$from, $to])
            ->sum('final_amount');
    }

    #[Computed]
    public function discountTotal(): int
    {
        [$from, $to] = $this->range();

        return (int) Invoice::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('discount');
    }

    #[Computed]
    public function expensesTotal(): int
    {
        [$from, $to] = $this->range();

        return (int) ShiftExpense::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');
    }

    #[Computed]
    public function doctorPayoutsTotal(): int
    {
        [$from, $to] = $this->range();

        return (int) DoctorShareLedger::query()
            ->whereBetween('paid_at', [$from, $to])
            ->sum('total_share');
    }

    #[Computed]
    public function impliedNet(): int
    {
        return $this->revenueTotal - $this->doctorPayoutsTotal - $this->expensesTotal;
    }

    #[Computed]
    public function openShift(): ?Shift
    {
        return Shift::query()
            ->where('status', ShiftStatus::Open)
            ->with('opener')
            ->first();
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/25">
        <div class="pointer-events-none absolute -end-16 -top-16 size-48 rounded-full bg-violet-400/10 blur-3xl dark:bg-violet-500/10"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Finance dashboard') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Collections, expenses, and doctor payouts for the selected period. Net is an operational approximation (paid invoices − payouts − shift expenses logged in the period).') }}
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

    @if ($this->openShift)
        <flux:callout icon="exclamation-triangle" color="amber">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <span>{{ __('A shift is currently open. Figures below include all activity in the date range, not only this shift.') }}</span>
                <flux:button :href="route('owner.shifts')" variant="ghost" size="sm" wire:navigate>
                    {{ __('View shift') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Paid invoice revenue') }}</flux:text>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney($this->revenueTotal) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Shift expenses (logged)') }}</flux:text>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney($this->expensesTotal) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Doctor payouts recorded') }}</flux:text>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-amber-800 dark:text-amber-300">{{ $this->formatMoney($this->doctorPayoutsTotal) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Discounts (all statuses)') }}</flux:text>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ $this->formatMoney($this->discountTotal) }}</p>
        </div>
        <div class="rounded-2xl border border-violet-200 bg-violet-50/80 p-6 dark:border-violet-900/50 dark:bg-violet-950/30 sm:col-span-2 lg:col-span-2">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-violet-800 dark:text-violet-200">{{ __('Implied net (period)') }}</flux:text>
            <p class="mt-2 text-3xl font-bold tabular-nums text-violet-950 dark:text-violet-50">{{ $this->formatMoney($this->impliedNet) }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:button :href="route('finance.money-trail', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo])" variant="outline" wire:navigate>
            {{ __('Money trail') }}
        </flux:button>
        <flux:button :href="route('finance.exports')" variant="ghost" wire:navigate>
            {{ __('Exports') }}
        </flux:button>
    </div>
</div>
