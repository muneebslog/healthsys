<?php

use App\Models\DoctorShareLedger;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ledger batch')] class extends Component
{
    public DoctorShareLedger $ledger;

    public function mount(DoctorShareLedger $ledger): void
    {
        $this->ledger = $ledger->load([
            'doctor',
            'paidBy:id,name',
            'items.invoiceService.invoice.patient',
            'items.invoiceService.service',
            'items.invoiceService.doctor',
        ]);
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

@php($l = $this->ledger)
<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:button :href="route('finance.ledger')" variant="ghost" icon="arrow-left" wire:navigate class="w-fit">
            {{ __('Back to ledger') }}
        </flux:button>
    </div>

    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/20">
        <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
            {{ __('Payout batch #:id', ['id' => $l->id]) }}
        </flux:heading>
        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
            {{ $l->doctor?->name ?? '—' }} ·
            {{ $l->paid_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
        </flux:text>
        @if (filled($l->notes))
            <flux:text class="mt-2 text-sm text-zinc-500">{{ $l->notes }}</flux:text>
        @endif
    </header>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Period') }}</flux:text>
            <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                {{ $l->period_from?->format('M j, Y') }} — {{ $l->period_to?->format('M j, Y') }}
            </p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Paid by') }}</flux:text>
            <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">{{ $l->paidBy?->name ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-5 dark:border-amber-900/50 dark:bg-amber-950/30">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-amber-900 dark:text-amber-200">{{ __('Total') }}</flux:text>
            <p class="mt-2 text-2xl font-bold tabular-nums text-amber-950 dark:text-amber-50">{{ $this->formatMoney((int) $l->total_share) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:heading size="md">{{ __('Line items') }}</flux:heading>
        </div>
        @if ($l->items->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:text class="text-zinc-500">{{ __('No items.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Invoice') }}</th>
                            <th class="px-6 py-3">{{ __('Patient') }}</th>
                            <th class="px-6 py-3">{{ __('Service') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Doc share') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($l->items as $item)
                            @php($line = $item->invoiceService)
                            <tr wire:key="li-{{ $item->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">#{{ $line?->invoice_id }}</td>
                                <td class="px-6 py-3 font-medium text-zinc-900 dark:text-white">{{ $line?->invoice?->patient?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $line?->service?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-end tabular-nums font-medium text-amber-900 dark:text-amber-300">{{ $this->formatMoney((int) ($line?->doctor_share_amount ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="flex justify-end">
        <flux:button
            variant="outline"
            icon="printer"
            :href="route('reception.doctor-share-payout-receipt', $l)"
            target="_blank"
            rel="noopener noreferrer"
        >
            {{ __('Print receipt') }}
        </flux:button>
    </div>
</div>
