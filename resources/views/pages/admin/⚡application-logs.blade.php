<?php

use App\Enums\UserRole;
use App\Services\LaravelLogViewer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Application log')] class extends Component
{
    use WithPagination;

    public string $logFile = '';

    public bool $showDetailModal = false;

    public ?int $detailIndex = null;

    public function mount(LaravelLogViewer $viewer): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }

        $files = $viewer->listLogFiles();
        $this->logFile = $files[0] ?? '';
    }

    public function updatedLogFile(): void
    {
        $this->resetPage();
    }

    public function updatedShowDetailModal(bool $value): void
    {
        if (! $value) {
            $this->detailIndex = null;
        }
    }

    public function openDetail(int $index): void
    {
        $this->detailIndex = $index;
        $this->showDetailModal = true;
    }

    public function getLogEntriesProperty()
    {
        return app(LaravelLogViewer::class)->paginateErrors(
            $this->logFile === '' ? null : $this->logFile,
            20
        );
    }

    public function getDetailEntryProperty(): ?array
    {
        if ($this->detailIndex === null) {
            return null;
        }

        $items = $this->logEntries->items();

        return $items[$this->detailIndex] ?? null;
    }

    public function getLogTruncatedProperty(): bool
    {
        return app(LaravelLogViewer::class)->logFileExceedsReadLimit(
            $this->logFile === '' ? null : $this->logFile
        );
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-rose-50/30 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-rose-950/20">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-rose-400/10 blur-3xl dark:bg-rose-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Application log') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('ERROR and higher-severity lines from storage/logs. Newest first. Large files only load the tail (see notice below).') }}
                </flux:text>
            </div>
            @if ($this->logFile !== '')
                <flux:badge color="zinc" class="shrink-0">
                    {{ __('Entries on this page') }}: {{ $this->logEntries->total() }}
                </flux:badge>
            @endif
        </div>
    </header>

    @if ($this->logTruncated && $this->logFile !== '')
        <flux:callout color="amber" icon="information-circle">
            {{ __('This log file is larger than the read limit; only the most recent portion is parsed. Increase HMS_LOG_VIEWER_MAX_BYTES in .env if you need more (uses more memory).') }}
            <span class="mt-1 block font-mono text-xs opacity-90">
                {{ __('Limit') }}: {{ number_format(app(LaravelLogViewer::class)->maxReadBytes()) }} bytes
            </span>
        </flux:callout>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-[12rem] max-w-md flex-1">
            <flux:field>
                <flux:label>{{ __('Log file') }}</flux:label>
                @php($files = app(LaravelLogViewer::class)->listLogFiles())
                @if ($files === [])
                    <flux:text class="text-sm text-zinc-500">{{ __('No .log files found in storage/logs.') }}</flux:text>
                @else
                    <flux:select wire:model.live="logFile" placeholder="{{ __('Choose a log file') }}">
                        @foreach ($files as $name)
                            <flux:select.option value="{{ $name }}" wire:key="log-opt-{{ $name }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </flux:field>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        @if ($this->logFile === '' || $files === [])
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('There is nothing to show yet. Laravel will create laravel.log (or daily logs) when something is logged.') }}</flux:text>
            </div>
        @elseif ($this->logEntries->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No ERROR-level (or higher) entries in the loaded portion of this file.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('When') }}</th>
                            <th class="px-6 py-3">{{ __('Level') }}</th>
                            <th class="px-6 py-3">{{ __('Channel') }}</th>
                            <th class="px-6 py-3">{{ __('Message') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->logEntries as $idx => $row)
                            <tr wire:key="app-log-{{ $this->logFile }}-{{ $this->logEntries->currentPage() }}-{{ $idx }}" class="align-top hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                    @if ($row['datetime'])
                                        {{ $row['datetime']->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                    @else
                                        {{ $row['datetime_raw'] }}
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge color="rose">{{ $row['level'] }}</flux:badge>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $row['channel'] }}
                                </td>
                                <td class="max-w-md px-6 py-4">
                                    <div class="line-clamp-3 whitespace-pre-wrap break-words font-mono text-xs text-zinc-800 dark:text-zinc-200">
                                        {{ Str::limit($row['body'], 280) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-end">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="openDetail({{ $idx }})">
                                        {{ __('View') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->logEntries->hasPages())
                <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    {{ $this->logEntries->links() }}
                </div>
            @endif
        @endif
    </div>

    <flux:modal wire:model="showDetailModal" name="application-log-detail" class="min-w-[20rem] max-w-4xl">
        @if ($this->detailEntry)
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Log entry') }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    <flux:badge color="rose">{{ $this->detailEntry['level'] }}</flux:badge>
                    <flux:badge color="zinc">{{ $this->detailEntry['channel'] }}</flux:badge>
                    @if ($this->detailEntry['datetime'])
                        <flux:badge color="zinc">
                            {{ $this->detailEntry['datetime']->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                        </flux:badge>
                    @endif
                </div>
                <pre class="max-h-[min(32rem,70vh)] overflow-auto rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-xs leading-relaxed whitespace-pre-wrap break-words text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">{{ $this->detailEntry['body'] }}</pre>
            </div>
        @endif
    </flux:modal>
</div>
