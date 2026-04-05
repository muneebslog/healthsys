<?php

use App\Enums\UserRole;
use App\Models\Queue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Queue insights')] class extends Component
{
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }

        $this->dateFrom = now()->subDays(6)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function formatDt(?CarbonInterface $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $value->timezone(config('app.timezone'))->format('M j, Y g:i A');
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function resolvedDateRange(): array
    {
        try {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->endOfDay();
        } catch (\Throwable) {
            $from = now()->subDays(6)->startOfDay();
            $to = now()->endOfDay();
        }

        if ($from->gt($to)) {
            return [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    #[Computed]
    public function queues()
    {
        [$from, $to] = $this->resolvedDateRange();

        return Queue::query()
            ->with(['service', 'doctor', 'shift'])
            ->withCount('tokens')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->paginate(20);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-sky-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-sky-950/25">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-sky-400/15 blur-3xl dark:bg-sky-500/10"></div>
        <div class="relative flex flex-col gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                        {{ __('Queue insights') }}
                    </flux:heading>
                    <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                        {{ __('Review historical token queues: when each line opened and closed. Open a queue to see every token’s arrival, call, completion, and payment times.') }}
                    </flux:text>
                </div>
                <flux:badge color="zinc" class="shrink-0">
                    {{ __('Queues in range') }}: {{ $this->queues->total() }}
                </flux:badge>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <flux:field>
                    <flux:label>{{ __('From') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateFrom" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('To') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateTo" />
                </flux:field>
                <div class="sm:col-span-2 rounded-xl border border-dashed border-zinc-200 bg-white/60 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800/40">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Filter') }}</flux:text>
                    <flux:text class="text-sm text-zinc-700 dark:text-zinc-300">
                        {{ __('Queues are listed when the queue row was created (line opened) within this range.') }}
                    </flux:text>
                </div>
            </div>
        </div>
    </header>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        @if ($this->queues->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No queues in this date range.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Opened') }}</th>
                            <th class="px-6 py-3">{{ __('Closed') }}</th>
                            <th class="px-6 py-3">{{ __('Service') }}</th>
                            <th class="px-6 py-3">{{ __('Doctor / line') }}</th>
                            <th class="px-6 py-3">{{ __('Shift opened') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 tabular-nums">{{ __('Tokens') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Token insights') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->queues as $row)
                            <tr wire:key="queue-insight-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $this->formatDt($row->created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $row->closed_at ? $this->formatDt($row->closed_at) : __('Still open') }}
                                </td>
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">
                                    {{ $row->service?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-zinc-700 dark:text-zinc-300">
                                    {{ $row->doctor?->name ?? __('General') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $row->shift?->opened_at ? $this->formatDt($row->shift->opened_at) : '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row->status->value === 'active')
                                        <flux:badge color="lime">{{ __('Active') }}</flux:badge>
                                    @elseif ($row->status->value === 'closed')
                                        <flux:badge color="zinc">{{ __('Closed') }}</flux:badge>
                                    @else
                                        <flux:badge color="sky">{{ __('Finished') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 tabular-nums text-zinc-700 dark:text-zinc-300">
                                    {{ $row->tokens_count }}
                                </td>
                                <td class="px-6 py-4 text-end">
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="arrow-top-right-on-square"
                                        :href="route('admin.queue-insights.show', $row)"
                                        wire:navigate
                                    >
                                        {{ __('View tokens') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->queues->hasPages())
                <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    {{ $this->queues->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
