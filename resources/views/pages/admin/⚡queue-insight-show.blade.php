<?php

use App\Enums\UserRole;
use App\Models\Queue;
use App\Models\QueueToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Queue token insights')] class extends Component
{
    public Queue $queue;

    public function mount(Queue $queue): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }

        $this->queue = $queue->load(['service', 'doctor', 'shift']);
    }

    public function formatDt(?CarbonInterface $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $value->timezone(config('app.timezone'))->format('M j, Y g:i A');
    }

    #[Computed]
    public function tokens()
    {
        return QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->with('patient')
            ->orderBy('token_number')
            ->get();
    }
}; ?>

@php($q = $this->queue)
<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:button :href="route('admin.queue-insights')" variant="ghost" icon="arrow-left" class="w-fit" wire:navigate>
            {{ __('Back to queue insights') }}
        </flux:button>
    </div>

    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-sky-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-sky-950/25">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-sky-400/15 blur-3xl dark:bg-sky-500/10"></div>
        <div class="relative space-y-2">
            <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                {{ $q->service?->name ?? '—' }}
            </flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ $q->doctor?->name ?? __('General') }}
                ·
                {{ __('Queue #:id', ['id' => $q->id]) }}
            </flux:text>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-500">
                {{ __('Opened') }} {{ $this->formatDt($q->created_at) }}
                @if ($q->closed_at)
                    · {{ __('Closed') }} {{ $this->formatDt($q->closed_at) }}
                @else
                    · {{ __('Still open') }}
                @endif
                @if ($q->shift?->opened_at)
                    · {{ __('Shift opened') }} {{ $this->formatDt($q->shift->opened_at) }}
                @endif
            </flux:text>
        </div>
    </header>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Line status') }}</flux:text>
            <p class="mt-2">
                @if ($q->status->value === 'active')
                    <flux:badge color="lime">{{ __('Active') }}</flux:badge>
                @elseif ($q->status->value === 'closed')
                    <flux:badge color="zinc">{{ __('Closed') }}</flux:badge>
                @else
                    <flux:badge color="sky">{{ __('Finished') }}</flux:badge>
                @endif
            </p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Tokens') }}</flux:text>
            <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $this->tokens->count() }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Current counters') }}</flux:text>
            <p class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">
                {{ __('Next #') }} {{ $q->current_token }}
                ·
                {{ __('Flow') }} {{ $q->current_flow_token }}
            </p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:heading size="md">{{ __('All tokens — arrival, call, completion, payment') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Reserved / arrived is when the token was issued. Called is when the patient was summoned to the counter (serving started).') }}
            </flux:text>
        </div>

        @if ($this->tokens->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No tokens on this queue.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">#</th>
                            <th class="px-6 py-3">{{ __('Patient') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3">{{ __('Reserved / arrived') }}</th>
                            <th class="px-6 py-3">{{ __('Called (served)') }}</th>
                            <th class="px-6 py-3">{{ __('Completed') }}</th>
                            <th class="px-6 py-3">{{ __('Paid') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->tokens as $token)
                            <tr wire:key="queue-show-token-{{ $token->id }}" class="align-top">
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-base font-semibold tabular-nums text-zinc-900 dark:text-white">
                                    {{ $token->token_number }}
                                </td>
                                <td class="px-6 py-4 text-zinc-700 dark:text-zinc-300">
                                    {{ $token->patient?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
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
                                <td class="whitespace-nowrap px-6 py-4 text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $this->formatDt($token->reserved_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $this->formatDt($token->called_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $this->formatDt($token->completed_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $this->formatDt($token->paid_at) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
