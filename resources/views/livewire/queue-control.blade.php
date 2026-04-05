<div wire:poll.4s.keep-alive="syncQueueFromServer" class="mx-auto max-w-2xl space-y-8 px-4 py-6 pb-24 sm:px-6 lg:max-w-4xl lg:px-8">
    {{-- Control strip: queue identity + stats + actions (mobile-first, large tap targets) --}}
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/90 bg-gradient-to-br from-zinc-50 via-white to-teal-50/35 p-6 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-teal-950/25 sm:p-8">
        <div class="pointer-events-none absolute -end-20 -top-20 size-56 rounded-full bg-teal-400/10 blur-3xl dark:bg-teal-500/10"></div>
        <div class="relative space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <flux:button :href="route('queues.index')" variant="ghost" size="sm" icon="arrow-left" class="mb-3 -ms-1 w-fit" wire:navigate>
                        {{ __('All queues') }}
                    </flux:button>
                    <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                        {{ $this->queue->doctor?->name ?? __('General') }}
                    </flux:heading>
                    <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                        {{ $this->queue->service?->name ?? __('Service') }}
                    </flux:text>
                </div>
                <flux:badge color="teal" class="shrink-0 self-start">{{ __('Live') }}</flux:badge>
            </div>

            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-zinc-200/80 bg-white/90 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800/80">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Now serving') }}</flux:text>
                    <p class="mt-1 text-lg font-bold tabular-nums text-teal-700 dark:text-teal-300">
                        @if ($this->servingToken)
                            T-{{ $this->servingToken->token_number }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-zinc-200/80 bg-white/90 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800/80">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Waiting') }}</flux:text>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->waitingTokens->count() }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200/80 bg-white/90 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800/80">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Skipped') }}</flux:text>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->skippedCount }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200/80 bg-white/90 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800/80">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Done') }}</flux:text>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->doneCount }}</p>
                </div>
            </dl>

            <flux:error name="control" />

            <div class="flex flex-col gap-3">
                <flux:button variant="primary" icon="arrow-right-circle" class="min-h-14 w-full text-base font-semibold sm:min-h-12" wire:click="callNext" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="callNext">{{ __('Call next') }}</span>
                    <span wire:loading wire:target="callNext">{{ __('Updating…') }}</span>
                </flux:button>
                <div class="grid grid-cols-2 gap-3">
                    <flux:button variant="outline" icon="arrow-uturn-left" class="min-h-12 w-full" wire:click="previous" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="previous">{{ __('Back') }}</span>
                        <span wire:loading wire:target="previous">…</span>
                    </flux:button>
                    <flux:button variant="outline" icon="forward" class="min-h-12 w-full" wire:click="skip" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="skip">{{ __('Skip') }}</span>
                        <span wire:loading wire:target="skip">…</span>
                    </flux:button>
                </div>
                @if ($this->canCloseQueue)
                    <flux:button variant="outline" icon="x-circle" class="min-h-12 w-full border-rose-200 text-rose-800 hover:bg-rose-50 dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/40" wire:click="openCloseQueueModal">
                        {{ __('Close queue') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </header>

    {{-- Tabs --}}
    <section class="space-y-4">
        <flux:heading size="lg">{{ __('Patient list') }}</flux:heading>
        <div class="flex flex-wrap gap-2 rounded-xl border border-zinc-200 bg-zinc-50/80 p-1.5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:button
                type="button"
                size="sm"
                class="flex-1 min-w-[5.5rem] sm:flex-none"
                :variant="$activeTab === 'waiting' ? 'primary' : 'ghost'"
                wire:click="setTab('waiting')"
            >
                {{ __('Waiting') }}
            </flux:button>
            <flux:button
                type="button"
                size="sm"
                class="flex-1 min-w-[5.5rem] sm:flex-none"
                :variant="$activeTab === 'all' ? 'primary' : 'ghost'"
                wire:click="setTab('all')"
            >
                {{ __('All') }}
            </flux:button>
            <flux:button
                type="button"
                size="sm"
                class="flex-1 min-w-[5.5rem] sm:flex-none"
                :variant="$activeTab === 'skipped' ? 'primary' : 'ghost'"
                wire:click="setTab('skipped')"
            >
                {{ __('Skipped') }}
            </flux:button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid grid-cols-12 gap-2 border-b border-zinc-100 bg-zinc-50/90 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                <span class="col-span-2 sm:col-span-2">{{ __('Token') }}</span>
                <span class="col-span-5 sm:col-span-5">{{ __('Patient') }}</span>
                <span class="col-span-3 sm:col-span-3">{{ __('Arrived') }}</span>
                <span class="col-span-2 text-end sm:col-span-2">{{ __('Action') }}</span>
            </div>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @if ($activeTab === 'waiting')
                    @forelse ($this->waitingTokens as $token)
                        <li wire:key="wait-{{ $token->id }}" class="grid grid-cols-12 items-center gap-2 px-4 py-3.5">
                            <span class="col-span-2 font-mono text-sm font-semibold text-teal-700 dark:text-teal-300">T-{{ $token->token_number }}</span>
                            <span class="col-span-5 truncate text-sm text-zinc-800 dark:text-zinc-200">{{ $token->patient?->name ?? '—' }}</span>
                            <span class="col-span-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->formatArrivedAt($token) }}</span>
                            <span class="col-span-2 text-end text-sm text-zinc-400">—</span>
                        </li>
                    @empty
                        <li class="px-4 py-12 text-center">
                            <flux:text class="text-zinc-500">{{ __('No one waiting right now.') }}</flux:text>
                        </li>
                    @endforelse
                @elseif ($activeTab === 'all')
                    @forelse ($this->allTokens as $token)
                        <li wire:key="all-{{ $token->id }}" class="grid grid-cols-12 items-center gap-2 px-4 py-3.5">
                            <span class="col-span-2 font-mono text-sm font-semibold text-zinc-800 dark:text-zinc-200">T-{{ $token->token_number }}</span>
                            <span class="col-span-5 truncate text-sm text-zinc-800 dark:text-zinc-200">{{ $token->patient?->name ?? '—' }}</span>
                            <span class="col-span-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->formatArrivedAt($token) }}</span>
                            <span class="col-span-2 text-end">
                                <flux:badge size="sm" color="zinc">{{ str_replace('_', ' ', $token->status->value) }}</flux:badge>
                            </span>
                        </li>
                    @empty
                        <li class="px-4 py-12 text-center">
                            <flux:text class="text-zinc-500">{{ __('No tokens in this queue yet.') }}</flux:text>
                        </li>
                    @endforelse
                @else
                    @forelse ($this->skippedTokens as $token)
                        <li wire:key="skip-{{ $token->id }}" class="grid grid-cols-12 items-center gap-2 px-4 py-3.5">
                            <span class="col-span-2 font-mono text-sm font-semibold text-amber-800 dark:text-amber-300">T-{{ $token->token_number }}</span>
                            <span class="col-span-5 truncate text-sm text-zinc-800 dark:text-zinc-200">{{ $token->patient?->name ?? '—' }}</span>
                            <span class="col-span-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->formatArrivedAt($token) }}</span>
                            <span class="col-span-2 flex justify-end">
                                <flux:button size="sm" variant="outline" wire:click="requeue({{ $token->id }})" wire:loading.attr="disabled">
                                    {{ __('Re-queue') }}
                                </flux:button>
                            </span>
                        </li>
                    @empty
                        <li class="px-4 py-12 text-center">
                            <flux:text class="text-zinc-500">{{ __('No skipped tokens.') }}</flux:text>
                        </li>
                    @endforelse
                @endif
            </ul>
        </div>
    </section>

    @if ($this->canCloseQueue)
        <flux:modal wire:model="showCloseQueueModal" name="close-queue" class="min-w-[22rem] max-w-lg">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Close this queue?') }}</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ __('This stops token calling for this line. A new queue for the same service will appear automatically when the next walk-in checks in or an appointment reserves a token—nothing is created right now.') }}
                </flux:text>
                @if ($this->isDailyResetService)
                    <flux:callout color="amber" icon="exclamation-triangle">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Daily queue service') }}</div>
                        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                            {{ __('This service uses a daily queue. Reserved appointments and token numbers on this closed queue stay on the closed record; new check-ins start a fresh queue. Only close if you understand today\'s appointments and displays.') }}
                        </flux:text>
                    </flux:callout>
                @endif
                <ul class="space-y-1 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/50">
                    <li class="flex justify-between gap-4">
                        <span class="text-zinc-500">{{ __('Waiting') }}</span>
                        <span class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ $this->waitingTokens->count() }}</span>
                    </li>
                    <li class="flex justify-between gap-4">
                        <span class="text-zinc-500">{{ __('Reserved (appointments)') }}</span>
                        <span class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ $this->reservedTokensCount }}</span>
                    </li>
                </ul>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:button variant="ghost" wire:click="$set('showCloseQueueModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="confirmCloseQueue" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="confirmCloseQueue">{{ __('Close queue') }}</span>
                        <span wire:loading wire:target="confirmCloseQueue">{{ __('Closing…') }}</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
