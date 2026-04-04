<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Exports')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function exportUrl(string $type): string
    {
        return route('finance.export.download', [
            'type' => $type,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ]);
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/20">
        <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
            {{ __('CSV exports') }}
        </flux:heading>
        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
            {{ __('Download UTF-8 CSV files for the selected period. Opens in Excel or Google Sheets.') }}
        </flux:text>
    </header>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('From') }}</flux:label>
                <flux:input type="date" wire:model.live="dateFrom" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('To') }}</flux:label>
                <flux:input type="date" wire:model.live="dateTo" />
            </flux:field>
        </div>

        <flux:separator class="my-6" />

        <ul class="space-y-3">
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Invoices') }}</flux:text>
                <flux:button variant="outline" size="sm" icon="arrow-down-tray" href="{{ $this->exportUrl('invoices') }}">
                    {{ __('Download') }}
                </flux:button>
            </li>
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Shift expenses') }}</flux:text>
                <flux:button variant="outline" size="sm" icon="arrow-down-tray" href="{{ $this->exportUrl('expenses') }}">
                    {{ __('Download') }}
                </flux:button>
            </li>
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Doctor payout ledger') }}</flux:text>
                <flux:button variant="outline" size="sm" icon="arrow-down-tray" href="{{ $this->exportUrl('ledger') }}">
                    {{ __('Download') }}
                </flux:button>
            </li>
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Shift summaries') }}</flux:text>
                <flux:button variant="outline" size="sm" icon="arrow-down-tray" href="{{ $this->exportUrl('shifts') }}">
                    {{ __('Download') }}
                </flux:button>
            </li>
        </ul>
    </div>
</div>
