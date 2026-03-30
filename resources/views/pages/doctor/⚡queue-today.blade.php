<?php

use App\Enums\QueueTokenStatus;
use App\Livewire\Concerns\GuardsDoctorAccess;
use App\Models\Doctor;
use App\Models\Queue;
use App\Models\QueueToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Today queue')] class extends Component
{
    use GuardsDoctorAccess;

    public Doctor $doctor;

    public function mount(): void
    {
        $this->doctor = $this->doctorProfile();
    }

    #[Computed]
    public function todayDateLabel(): string
    {
        return now()->timezone(config('app.timezone'))->format('l, M j, Y');
    }

    #[Computed]
    public function queuesToday()
    {
        $doctorId = $this->doctor->id;
        $today = now()->toDateString();

        $queueIds = QueueToken::query()
            ->whereHas('queue', fn ($q) => $q->where('doctor_id', $doctorId))
            ->whereDate('created_at', $today)
            ->distinct()
            ->pluck('queue_id');

        if ($queueIds->isEmpty()) {
            return collect();
        }

        return Queue::query()
            ->whereIn('id', $queueIds)
            ->with('service:id,name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\QueueToken>>
     */
    #[Computed]
    public function tokensByQueue()
    {
        $doctorId = $this->doctor->id;
        $today = now()->toDateString();

        $tokens = QueueToken::query()
            ->whereHas('queue', fn ($q) => $q->where('doctor_id', $doctorId))
            ->whereDate('created_at', $today)
            ->with(['queue.service:id,name', 'patient:id,name'])
            ->orderBy('queue_id')
            ->orderBy('token_number')
            ->get();

        return $tokens->groupBy('queue_id');
    }

    public function statusLabel(QueueTokenStatus $status): string
    {
        return match ($status) {
            QueueTokenStatus::Reserved => __('Reserved'),
            QueueTokenStatus::Waiting => __('Waiting'),
            QueueTokenStatus::Serving => __('Serving'),
            QueueTokenStatus::Done => __('Done'),
            QueueTokenStatus::Skipped => __('Skipped'),
        };
    }

    public function tokenBadgeColor(QueueToken $token): string
    {
        return match ($token->status) {
            QueueTokenStatus::Serving => 'teal',
            QueueTokenStatus::Waiting => 'amber',
            QueueTokenStatus::Reserved => 'zinc',
            QueueTokenStatus::Done => 'green',
            QueueTokenStatus::Skipped => 'rose',
        };
    }
}; ?>

<div wire:poll.4s class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-teal-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-teal-950/25">
        <div class="pointer-events-none absolute -end-20 -top-20 size-56 rounded-full bg-teal-400/10 blur-3xl dark:bg-teal-500/10"></div>
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Today’s queue') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Tokens issued for your services today. Refreshes every few seconds.') }}
                </flux:text>
                <flux:text class="mt-2 text-sm font-medium text-teal-800 dark:text-teal-300">{{ $this->todayDateLabel }}</flux:text>
            </div>
            <flux:badge color="teal" class="shrink-0">{{ __('Live') }}</flux:badge>
        </div>
    </header>

    @if ($this->queuesToday->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/40 px-8 py-16 text-center dark:border-zinc-600 dark:bg-zinc-900/30">
            <flux:heading size="lg" class="mb-2 text-zinc-800 dark:text-zinc-200">{{ __('No tokens yet today') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('When patients check in for your services, their tokens will appear here.') }}
            </flux:text>
        </div>
    @else
        <div class="space-y-10">
            @foreach ($this->queuesToday as $queue)
                @php
                    $bucket = $this->tokensByQueue->get($queue->id, collect());
                @endphp
                <section wire:key="q-{{ $queue->id }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <flux:heading size="lg" class="text-zinc-900 dark:text-white">{{ $queue->service?->name ?? __('Service') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500">{{ __('Now serving') }} · T-{{ $queue->current_flow_token }}</flux:text>
                        </div>
                        <flux:badge color="zinc" class="tabular-nums">{{ $bucket->count() }} {{ __('tokens today') }}</flux:badge>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-zinc-100 dark:border-zinc-800">
                        <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            <thead class="bg-zinc-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="whitespace-nowrap px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Token') }}</th>
                                    <th class="px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Patient') }}</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Reserved') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($bucket as $token)
                                    <tr wire:key="tok-{{ $token->id }}">
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs font-medium text-zinc-900 dark:text-white">
                                            {{ __('T-:n', ['n' => $token->token_number]) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-zinc-800 dark:text-zinc-200">
                                            {{ $token->patient?->name ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5">
                                            <flux:badge :color="$this->tokenBadgeColor($token)" size="sm">
                                                {{ $this->statusLabel($token->status) }}
                                            </flux:badge>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-end text-zinc-500">
                                            @if ($token->reserved_at)
                                                {{ $token->reserved_at->timezone(config('app.timezone'))->format('g:i A') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
