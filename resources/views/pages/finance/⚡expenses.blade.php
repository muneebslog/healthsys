<?php

use App\Models\ShiftExpense;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Shift expenses')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(14)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function expenses()
    {
        $from = \Carbon\Carbon::parse($this->dateFrom)->startOfDay();
        $to = \Carbon\Carbon::parse($this->dateTo)->endOfDay();

        return ShiftExpense::query()
            ->with(['shift', 'creator'])
            ->whereBetween('created_at', [$from, $to])
            ->when(filled($this->search), function ($q): void {
                $term = trim($this->search);
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                $q->where('label', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function labelSubtotals(): \Illuminate\Support\Collection
    {
        $from = \Carbon\Carbon::parse($this->dateFrom)->startOfDay();
        $to = \Carbon\Carbon::parse($this->dateTo)->endOfDay();

        return ShiftExpense::query()
            ->selectRaw('label, SUM(amount) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('label')
            ->orderByDesc('total')
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
                    {{ __('Shift expenses') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('All expense lines logged against shifts, with subtotals by label.') }}
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

    @if ($this->labelSubtotals->isNotEmpty())
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="md" class="mb-4">{{ __('Subtotals by label') }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->labelSubtotals as $row)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-100 px-4 py-3 dark:border-zinc-800" wire:key="lbl-{{ $row->label }}">
                        <flux:text class="font-medium text-zinc-800 dark:text-zinc-200">{{ $row->label }}</flux:text>
                        <flux:text class="tabular-nums font-semibold text-zinc-900 dark:text-white">{{ $this->formatMoney((int) $row->total) }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:field class="max-w-md">
                <flux:label>{{ __('Filter label') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search label…') }}" />
            </flux:field>
        </div>

        @if ($this->expenses->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No expenses in this range.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('When') }}</th>
                            <th class="px-6 py-3">{{ __('Shift') }}</th>
                            <th class="px-6 py-3">{{ __('Label') }}</th>
                            <th class="px-6 py-3">{{ __('Logged by') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->expenses as $e)
                            <tr wire:key="exp-{{ $e->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $e->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-3">
                                    <flux:button size="sm" variant="ghost" :href="route('owner.shifts.show', $e->shift_id)" wire:navigate>
                                        #{{ $e->shift_id }}
                                    </flux:button>
                                </td>
                                <td class="px-6 py-3 font-medium text-zinc-900 dark:text-white">{{ $e->label }}</td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $e->creator?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-end tabular-nums font-semibold text-zinc-900 dark:text-white">{{ $this->formatMoney((int) $e->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($this->expenses->hasPages())
                <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    {{ $this->expenses->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
