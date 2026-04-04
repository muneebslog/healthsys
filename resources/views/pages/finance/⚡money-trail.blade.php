<?php

use App\Models\DoctorShareLedger;
use App\Models\Invoice;
use App\Models\ShiftExpense;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Money trail')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = request()->string('dateFrom', now()->toDateString())->toString();
        $this->dateTo = request()->string('dateTo', now()->toDateString())->toString();
    }

    /**
     * @return Collection<int, object{type: string, at: \Carbon\Carbon, label: string, amount: int, direction: string, ref: string}>
     */
    #[Computed]
    public function events(): Collection
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $rows = collect();

        Invoice::query()
            ->with(['patient', 'shift'])
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(250)
            ->get()
            ->each(function (Invoice $inv) use ($rows): void {
                $rows->push((object) [
                    'type' => 'invoice',
                    'at' => $inv->created_at,
                    'label' => __('Invoice #:id — :patient', ['id' => $inv->id, 'patient' => $inv->patient?->name ?? '—']),
                    'amount' => (int) $inv->final_amount,
                    'direction' => 'in',
                    'ref' => (string) $inv->status->value,
                ]);
            });

        ShiftExpense::query()
            ->with(['shift', 'creator'])
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(250)
            ->get()
            ->each(function (ShiftExpense $e) use ($rows): void {
                $rows->push((object) [
                    'type' => 'expense',
                    'at' => $e->created_at,
                    'label' => __('Expense: :label (shift #:sid)', ['label' => $e->label, 'sid' => $e->shift_id]),
                    'amount' => (int) $e->amount,
                    'direction' => 'out',
                    'ref' => $e->creator?->name ?? '—',
                ]);
            });

        DoctorShareLedger::query()
            ->with(['doctor', 'paidBy'])
            ->whereBetween('paid_at', [$from, $to])
            ->orderByDesc('paid_at')
            ->limit(250)
            ->get()
            ->each(function (DoctorShareLedger $l) use ($rows): void {
                $rows->push((object) [
                    'type' => 'payout',
                    'at' => $l->paid_at,
                    'label' => __('Doctor payout: :name', ['name' => $l->doctor?->name ?? '—']),
                    'amount' => (int) $l->total_share,
                    'direction' => 'out',
                    'ref' => $l->paidBy?->name ?? '—',
                ]);
            });

        return $rows->sortByDesc(fn (object $e): int => $e->at->timestamp)->values();
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/20">
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Money trail') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Collections, shift expenses, and doctor payout batches in the period (newest first).') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <flux:field>
                    <flux:label>{{ __('From') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateFrom" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('To') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateTo" />
                </flux:field>
            </div>
        </div>
    </header>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        @if ($this->events->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No movements in this range.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Time') }}</th>
                            <th class="px-6 py-3">{{ __('Type') }}</th>
                            <th class="px-6 py-3">{{ __('Detail') }}</th>
                            <th class="px-6 py-3">{{ __('Ref') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->events as $e)
                            <tr wire:key="mt-{{ $e->type }}-{{ $e->at->timestamp }}-{{ $loop->index }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $e->at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-3">
                                    @php($typeColor = $e->type === 'invoice' ? 'lime' : ($e->type === 'payout' ? 'amber' : 'zinc'))
                                    <flux:badge size="sm" color="{{ $typeColor }}">{{ $e->type }}</flux:badge>
                                </td>
                                <td class="px-6 py-3 text-zinc-800 dark:text-zinc-200">{{ $e->label }}</td>
                                <td class="px-6 py-3 text-zinc-500">{{ $e->ref }}</td>
                                <td class="px-6 py-3 text-end tabular-nums font-semibold {{ $e->direction === 'in' ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300' }}">
                                    {{ $e->direction === 'in' ? '+' : '−' }}{{ $this->formatMoney($e->amount) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
