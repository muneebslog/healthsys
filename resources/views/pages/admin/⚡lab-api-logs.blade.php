<?php

use App\Enums\UserRole;
use App\Models\LabApiRequestLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Lab API log')] class extends Component
{
    use WithPagination;

    public bool $showDetailModal = false;

    public ?int $detailLogId = null;

    public function mount(): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }
    }

    public function updatedShowDetailModal(bool $value): void
    {
        if (! $value) {
            $this->detailLogId = null;
        }
    }

    public function openDetail(int $id): void
    {
        $this->detailLogId = $id;
        $this->showDetailModal = true;
    }

    #[Computed]
    public function logs()
    {
        return LabApiRequestLog::query()
            ->with('invoice')
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function detailLog(): ?LabApiRequestLog
    {
        if ($this->detailLogId === null) {
            return null;
        }

        return LabApiRequestLog::query()->find($this->detailLogId);
    }

    public function prettyResponseBody(?string $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : $body;
        }

        return $body;
    }

    public function prettyRequestBody(array $body): string
    {
        $encoded = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : '{}';
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/25">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-violet-400/15 blur-3xl dark:bg-violet-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Lab API log') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Outbound calls to the external lab HMS: request payload (headers redact the bearer token) and raw response.') }}
                </flux:text>
            </div>
            <flux:badge color="zinc" class="shrink-0">
                {{ __('Total') }}: {{ $this->logs->total() }}
            </flux:badge>
        </div>
    </header>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        @if ($this->logs->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No lab API requests recorded yet.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('When') }}</th>
                            <th class="px-6 py-3">{{ __('Invoice') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3">{{ __('HTTP') }}</th>
                            <th class="px-6 py-3">{{ __('Duration') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->logs as $row)
                            <tr wire:key="lab-api-log-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                    {{ $row->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row->invoice_id)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            class="font-mono tabular-nums"
                                            :href="route('invoices.print', $row->invoice_id)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            #{{ $row->invoice_id }}
                                        </flux:button>
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row->succeeded)
                                        <flux:badge color="lime">{{ __('OK') }}</flux:badge>
                                    @elseif ($row->error_message)
                                        <flux:badge color="rose">{{ __('Error') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber">{{ __('Failed') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $row->response_status ?? '—' }}
                                </td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">
                                    @if ($row->duration_ms !== null)
                                        {{ $row->duration_ms }} ms
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-end">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="openDetail({{ $row->id }})">
                                        {{ __('View') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->logs->hasPages())
                <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    {{ $this->logs->links() }}
                </div>
            @endif
        @endif
    </div>

    <flux:modal wire:model="showDetailModal" name="lab-api-log-detail" class="min-w-[20rem] max-w-3xl">
        @if ($this->detailLog)
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Request / response') }}</flux:heading>

                <div class="space-y-1">
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('URL') }}</flux:text>
                    <div class="break-all font-mono text-sm text-zinc-800 dark:text-zinc-200">{{ $this->detailLog->url }}</div>
                </div>

                @if ($this->detailLog->error_message)
                    <flux:callout color="rose" icon="exclamation-triangle">
                        <div class="text-sm font-medium">{{ __('Client error') }}</div>
                        <div class="mt-1 whitespace-pre-wrap break-words font-mono text-xs">{{ $this->detailLog->error_message }}</div>
                    </flux:callout>
                @endif

                <div class="space-y-2">
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Request headers') }}</flux:text>
                    <pre class="max-h-48 overflow-auto rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-xs leading-relaxed text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">{{ json_encode($this->detailLog->request_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>

                <div class="space-y-2">
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Request body') }}</flux:text>
                    <pre class="max-h-64 overflow-auto rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-xs leading-relaxed text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">{{ $this->prettyRequestBody($this->detailLog->request_body ?? []) }}</pre>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Response HTTP status') }}</flux:text>
                        <div class="font-mono text-sm text-zinc-800 dark:text-zinc-200">{{ $this->detailLog->response_status ?? '—' }}</div>
                    </div>
                    <div class="space-y-1">
                        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Duration') }}</flux:text>
                        <div class="font-mono text-sm text-zinc-800 dark:text-zinc-200">
                            {{ $this->detailLog->duration_ms !== null ? $this->detailLog->duration_ms.' ms' : '—' }}
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Response body') }}</flux:text>
                    <pre class="max-h-80 overflow-auto rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-xs leading-relaxed text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">{{ $this->prettyResponseBody($this->detailLog->response_body) ?: '—' }}</pre>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
