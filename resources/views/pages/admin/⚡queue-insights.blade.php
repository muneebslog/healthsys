<?php

use App\Enums\UserRole;
use App\Models\Queue;
use App\Models\QueueToken;
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

    public bool $showTokensModal = false;

    public ?int $selectedQueueId = null;

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

    public function updatedShowTokensModal(bool $value): void
    {
        if (! $value) {
            $this->selectedQueueId = null;
        }
    }

    public function openTokens(int $queueId): void
    {
        $this->selectedQueueId = $queueId;
        $this->showTokensModal = true;
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

    #[Computed]
    public function selectedQueue(): ?Queue
    {
        if ($this->selectedQueueId === null) {
            return null;
        }

        return Queue::query()
            ->with(['service', 'doctor', 'shift'])
            ->find($this->selectedQueueId);
    }

    #[Computed]
    public function selectedQueueTokens()
    {
        if ($this->selectedQueueId === null) {
            return collect();
        }

        return QueueToken::query()
            ->where('queue_id', $this->selectedQueueId)
            ->with('patient')
            ->orderBy('token_number')
            ->get();
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
                        {{ __('Review historical token queues: when each line opened and closed, then drill into tokens for reserved, called, completed, and paid times.') }}
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
                            <th class="px-6 py-3 text-end">{{ __('Details') }}</th>
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
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="openTokens({{ $row->id }})">
                                        {{ __('Tokens') }}
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

    <flux:modal wire:model="showTokensModal" name="queue-insight-tokens" class="min-w-[20rem] max-w-5xl">
        @if ($this->selectedQueue)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Tokens') }} — {{ $this->selectedQueue->service?->name ?? '—' }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                        {{ $this->selectedQueue->doctor?->name ?? __('General') }}
                        ·
                        {{ __('Queue opened') }} {{ $this->formatDt($this->selectedQueue->created_at) }}
                        @if ($this->selectedQueue->closed_at)
                            · {{ __('Closed') }} {{ $this->formatDt($this->selectedQueue->closed_at) }}
                        @endif
                    </flux:text>
                </div>

                @if ($this->selectedQueueTokens->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('No tokens on this queue.') }}</flux:text>
                @else
                    <div class="max-h-[min(28rem,70vh)] overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead class="sticky top-0 z-10 border-b border-zinc-100 bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/90">
                                <tr>
                                    <th class="px-4 py-2.5">#</th>
                                    <th class="px-4 py-2.5">{{ __('Patient') }}</th>
                                    <th class="px-4 py-2.5">{{ __('Status') }}</th>
                                    <th class="px-4 py-2.5">{{ __('Reserved / arrived') }}</th>
                                    <th class="px-4 py-2.5">{{ __('Called') }}</th>
                                    <th class="px-4 py-2.5">{{ __('Completed') }}</th>
                                    <th class="px-4 py-2.5">{{ __('Paid') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($this->selectedQueueTokens as $token)
                                    <tr wire:key="queue-insight-token-{{ $token->id }}" class="align-top">
                                        <td class="whitespace-nowrap px-4 py-3 font-mono tabular-nums font-semibold text-zinc-900 dark:text-white">
                                            {{ $token->token_number }}
                                        </td>
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                            {{ $token->patient?->name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($token->status->value === 'done')
                                                <flux:badge color="lime">{{ __('Done') }}</flux:badge>
                                            @elseif ($token->status->value === 'serving')
                                                <flux:badge color="amber">{{ __('Serving') }}</flux:badge>
                                            @elseif ($token->status->value === 'waiting')
                                                <flux:badge color="zinc">{{ __('Waiting') }}</flux:badge>
                                            @elseif ($token->status->value === 'reserved')
                                                <flux:badge color="sky">{{ __('Reserved') }}</flux:badge>
                                            @else
                                                <flux:badge color="zinc">{{ __('Skipped') }}</flux:badge>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $this->formatDt($token->reserved_at) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $this->formatDt($token->called_at) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $this->formatDt($token->completed_at) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $this->formatDt($token->paid_at) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
