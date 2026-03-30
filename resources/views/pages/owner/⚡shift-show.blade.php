<?php

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shift')] class extends Component
{
    public Shift $shift;

    public function mount(Shift $shift): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && $role !== UserRole::Owner) {
            abort(403);
        }

        $this->shift = $shift->load([
            'opener',
            'closer',
            'expenses' => fn ($q) => $q->orderByDesc('id'),
        ]);
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

@php($s = $this->shift)
<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:button :href="route('owner.shifts')" variant="ghost" icon="arrow-left" wire:navigate class="w-fit">
            {{ __('Back to shifts') }}
        </flux:button>
        @if ($s->status === ShiftStatus::Open)
            <flux:badge color="lime">{{ __('In progress') }}</flux:badge>
        @else
            <flux:badge color="zinc">{{ __('Closed') }}</flux:badge>
        @endif
    </div>

    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-amber-50/50 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-amber-950/20">
        <div class="pointer-events-none absolute -end-16 -top-16 size-48 rounded-full bg-amber-400/10 blur-3xl dark:bg-amber-500/10"></div>
        <div class="relative">
            <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                {{ __('Shift conclusion') }}
            </flux:heading>
            <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                {{ __('Line-by-line totals for this shift. Figures match what the system computes at close.') }}
            </flux:text>
        </div>
    </header>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="md" class="mb-4">{{ __('Shift details') }}</flux:heading>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Opened by') }}</flux:text>
                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->opener?->name }}</flux:text>
            </div>
            <div class="flex flex-col gap-1">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Opened at') }}</flux:text>
                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->opened_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</flux:text>
            </div>
            @if ($s->status === ShiftStatus::Closed)
                <div class="flex flex-col gap-1">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Closed by') }}</flux:text>
                    <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->closer?->name ?? '—' }}</flux:text>
                </div>
                <div class="flex flex-col gap-1">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Closed at') }}</flux:text>
                    <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->closed_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</flux:text>
                </div>
            @endif
        </dl>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Opening balance') }}</flux:text>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney((int) $s->opening_balance) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Invoices (paid)') }}</flux:text>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney($s->totalInvoices()) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Doctor shares (accrued)') }}</flux:text>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-amber-800 dark:text-amber-400">{{ $this->formatMoney($s->totalDoctorPayouts()) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Expenses') }}</flux:text>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney($s->totalExpenses()) }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50/90 p-6 dark:border-amber-900/50 dark:bg-amber-950/35">
        <flux:text class="text-xs font-medium uppercase tracking-wide text-amber-900 dark:text-amber-200">{{ __('Net') }}</flux:text>
        <p class="mt-2 text-3xl font-bold tabular-nums text-amber-950 dark:text-amber-50">{{ $this->formatMoney($s->netAmount()) }}</p>
        <flux:text class="mt-2 text-sm text-amber-900/80 dark:text-amber-200/90">
            {{ __('Opening + invoices − doctor shares − expenses') }}
        </flux:text>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:heading size="md">{{ __('Expense log') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ $s->expenses->count() }} {{ __('entries') }}</flux:text>
        </div>
        @if ($s->expenses->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:text class="text-zinc-500">{{ __('No expenses recorded for this shift.') }}</flux:text>
            </div>
        @else
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($s->expenses as $expense)
                    <li wire:key="exp-{{ $expense->id }}" class="flex items-center justify-between gap-4 px-6 py-3">
                        <flux:text class="font-medium text-zinc-800 dark:text-zinc-200">{{ $expense->label }}</flux:text>
                        <flux:text class="tabular-nums text-zinc-600 dark:text-zinc-400">{{ $this->formatMoney((int) $expense->amount) }}</flux:text>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
