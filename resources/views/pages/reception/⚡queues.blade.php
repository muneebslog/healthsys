<?php

use App\Enums\QueueTokenStatus;
use App\Enums\UserRole;
use App\Models\Queue;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Queues')] class extends Component
{
    public function mount(): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
            abort(403);
        }
    }

    #[Computed]
    public function activeQueues()
    {
        return Queue::query()
            ->active()
            ->with(['service:id,name', 'doctor:id,name'])
            ->withCount([
                'tokens as waiting_count' => fn ($q) => $q->where('status', QueueTokenStatus::Waiting),
            ])
            ->orderBy('id')
            ->get();
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/25">
        <div class="pointer-events-none absolute -end-16 -top-16 size-48 rounded-full bg-violet-400/10 blur-3xl dark:bg-violet-500/10"></div>
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Token queues') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Pick an active queue to call tokens, skip, or re-queue patients from your phone.') }}
                </flux:text>
            </div>
            <flux:badge color="violet" class="shrink-0">{{ __('Reception') }}</flux:badge>
        </div>
    </header>

    @if ($this->activeQueues->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/40 px-8 py-16 text-center dark:border-zinc-600 dark:bg-zinc-900/30">
            <flux:heading size="lg" class="mb-2 text-zinc-800 dark:text-zinc-200">{{ __('No active queues') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('Queues appear when patients check in on Walk-in or when appointments use this shift’s services.') }}
            </flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($this->activeQueues as $queue)
                <a
                    wire:key="queue-{{ $queue->id }}"
                    href="{{ route('queues.control', $queue) }}"
                    wire:navigate
                    class="group relative flex flex-col gap-4 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:border-violet-300/80 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-violet-600/50"
                >
                    <div class="pointer-events-none absolute -end-8 -top-8 size-24 rounded-full bg-violet-400/5 blur-2xl transition group-hover:bg-violet-400/10 dark:bg-violet-500/10"></div>
                    <div class="relative flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:heading size="lg" class="text-zinc-900 dark:text-white">
                                {{ $queue->doctor?->name ?? __('General') }}
                            </flux:heading>
                            <flux:text class="mt-0.5 text-zinc-600 dark:text-zinc-400">
                                {{ $queue->service?->name ?? __('Service') }}
                            </flux:text>
                        </div>
                        <flux:badge color="zinc" class="shrink-0 tabular-nums">
                            {{ $queue->waiting_count }} {{ __('waiting') }}
                        </flux:badge>
                    </div>
                    <div class="relative flex items-center justify-between gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <flux:text class="text-sm font-medium text-violet-700 dark:text-violet-300">
                            {{ __('Open control') }}
                        </flux:text>
                        <flux:icon.chevron-right class="size-5 text-zinc-400 transition group-hover:translate-x-0.5 group-hover:text-violet-500 dark:text-zinc-500" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
