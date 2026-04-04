<?php

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\DoctorShareLedger;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Shifts')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && $role !== UserRole::Owner) {
            abort(403);
        }
    }

    #[Computed]
    public function activeShift(): ?Shift
    {
        return Shift::query()
            ->where('status', ShiftStatus::Open)
            ->with(['opener', 'expenses' => fn ($q) => $q->orderByDesc('id')])
            ->first();
    }

    #[Computed]
    public function closedShifts()
    {
        return Shift::query()
            ->where('status', ShiftStatus::Closed)
            ->with(['opener', 'closer'])
            ->orderByDesc('closed_at')
            ->paginate(12);
    }

    #[Computed]
    public function todaysDoctorPayoutTotal(): int
    {
        return DoctorShareLedger::totalPaidToday();
    }

    #[Computed]
    public function todaysDoctorPayoutByDoctor(): \Illuminate\Support\Collection
    {
        return DoctorShareLedger::sumsByDoctorPaidToday();
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-amber-50/50 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-amber-950/20">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-amber-400/15 blur-3xl dark:bg-amber-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Shifts') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Independent view of the active shift and closed shift history — same figures staff see at close, without relying on narration.') }}
                </flux:text>
            </div>
            @if ($this->activeShift)
                <flux:badge color="lime" class="shrink-0">{{ __('Shift open') }}</flux:badge>
            @else
                <flux:badge color="zinc" class="shrink-0">{{ __('No active shift') }}</flux:badge>
            @endif
        </div>
    </header>

    @include('partials.shifts-todays-doctor-payout', [
        'total' => $this->todaysDoctorPayoutTotal,
        'breakdown' => $this->todaysDoctorPayoutByDoctor,
        'showShareOutLink' => false,
    ])

    @if ($this->activeShift)
        @php($s = $this->activeShift)
        <section class="space-y-6">
            <flux:heading size="lg">{{ __('Current shift') }}</flux:heading>
            <div class="grid gap-8 lg:grid-cols-5">
                <div class="lg:col-span-2 space-y-4">
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                        <dl class="space-y-4 text-sm">
                            <div class="flex justify-between gap-4">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Opened by') }}</flux:text>
                                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->opener?->name }}</flux:text>
                            </div>
                            <div class="flex justify-between gap-4">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Opened at') }}</flux:text>
                                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->opened_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</flux:text>
                            </div>
                            <flux:separator />
                            <div class="flex justify-between gap-4">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Opening balance') }}</flux:text>
                                <flux:text class="font-semibold tabular-nums text-amber-800 dark:text-amber-300">{{ $this->formatMoney((int) $s->opening_balance) }}</flux:text>
                            </div>
                        </dl>
                        <div class="mt-6">
                            <flux:button :href="route('owner.shifts.show', $s)" variant="outline" icon="arrow-top-right-on-square" wire:navigate class="w-full sm:w-auto">
                                {{ __('Open full summary') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-3 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
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
                        <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-5 dark:border-amber-900/50 dark:bg-amber-950/30">
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-amber-900 dark:text-amber-200">{{ __('Net (live)') }}</flux:text>
                            <p class="mt-2 text-2xl font-bold tabular-nums text-amber-950 dark:text-amber-100">{{ $this->formatMoney($s->netAmount()) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Previous shifts') }}</flux:heading>
                <flux:text class="mt-0.5 text-sm text-zinc-500">{{ __('Closed shifts, newest first. Use View for the full conclusion.') }}</flux:text>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            @if ($this->closedShifts->isEmpty())
                <div class="px-6 py-16 text-center">
                    <flux:text class="text-zinc-500">{{ __('No closed shifts yet.') }}</flux:text>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <tr>
                                <th class="px-6 py-3">{{ __('Opened') }}</th>
                                <th class="px-6 py-3">{{ __('Closed') }}</th>
                                <th class="px-6 py-3">{{ __('Opened by') }}</th>
                                <th class="px-6 py-3 text-end">{{ __('Net') }}</th>
                                <th class="px-6 py-3 text-end">{{ __('') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->closedShifts as $row)
                                <tr wire:key="shift-row-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                    <td class="px-6 py-4 text-zinc-700 dark:text-zinc-300">
                                        {{ $row->opened_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                    </td>
                                    <td class="px-6 py-4 text-zinc-700 dark:text-zinc-300">
                                        {{ $row->closed_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">{{ $row->opener?->name }}</td>
                                    <td class="px-6 py-4 text-end tabular-nums font-semibold text-amber-900 dark:text-amber-200">
                                        {{ $this->formatMoney($row->netAmount()) }}
                                    </td>
                                    <td class="px-6 py-4 text-end">
                                        <flux:button size="sm" variant="ghost" :href="route('owner.shifts.show', $row)" wire:navigate>
                                            {{ __('View') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($this->closedShifts->hasPages())
                    <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                        {{ $this->closedShifts->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>
</div>
