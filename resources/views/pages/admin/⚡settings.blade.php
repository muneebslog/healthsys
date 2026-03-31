<?php

use App\Enums\UserRole;
use App\Services\QueueNormalizationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin settings')] class extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $lastReport = null;

    public function mount(): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }
    }

    public function normalizeDb(): void
    {
        $this->lastReport = app(QueueNormalizationService::class)->normalizeActiveQueues();
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-emerald-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950/20">
        <div class="pointer-events-none absolute -end-16 -top-20 size-48 rounded-full bg-emerald-400/15 blur-3xl dark:bg-emerald-500/10"></div>
        <div class="relative flex items-end justify-between gap-4">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Settings') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Admin-only maintenance actions for keeping queues and tokens consistent.') }}
                </flux:text>
            </div>
        </div>
    </header>

    <flux:card class="space-y-4 p-6 sm:p-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Normalize DB') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Closes empty duplicate active queues and merges non-conflicting tokens into the primary queue (per doctor + service + shift).') }}
                </flux:text>
            </div>
            <flux:button
                variant="primary"
                icon="wrench-screwdriver"
                type="button"
                wire:click="normalizeDb"
                wire:confirm="{{ __('Run normalization now? This affects active queues.') }}"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="normalizeDb">{{ __('Normalize now') }}</span>
                <span wire:loading wire:target="normalizeDb">{{ __('Working…') }}</span>
            </flux:button>
        </div>

        @if ($lastReport)
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Duplicate groups') }}</flux:text>
                    <div class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $lastReport['scanned_groups'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Empty queues closed') }}</flux:text>
                    <div class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $lastReport['closed_empty_duplicates'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Tokens merged') }}</flux:text>
                    <div class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $lastReport['merged_tokens'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Queues closed (after merge)') }}</flux:text>
                    <div class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $lastReport['closed_after_merge'] ?? 0 }}</div>
                </div>
            </div>

            @php($conflicts = $lastReport['conflicts'] ?? [])
            @if (count($conflicts) > 0)
                <flux:callout color="amber" icon="exclamation-triangle" class="mt-4">
                    <div class="space-y-2">
                        <div class="font-semibold">{{ __('Some duplicates could not be merged due to token number conflicts.') }}</div>
                        <div class="text-sm">{{ __('Those queues were left active so no tokens are lost. Resolve conflicts manually (or we can build a “renumber” flow).') }}</div>
                    </div>
                </flux:callout>

                <div class="mt-3 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Primary queue') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Conflicting queue') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Shift') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Service') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Doctor') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-300">{{ __('Token numbers') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($conflicts as $row)
                                <tr>
                                    <td class="px-4 py-3 font-mono">{{ $row['primary_queue_id'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['conflicting_queue_id'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['shift_id'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['service_id'] }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['doctor_id'] ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono">{{ implode(', ', $row['token_numbers'] ?? []) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:callout color="lime" icon="check-circle" class="mt-4">
                    {{ __('Normalization completed with no conflicts.') }}
                </flux:callout>
            @endif
        @endif
    </flux:card>
</div>

