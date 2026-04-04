<?php

use App\Models\Doctor;
use App\Models\DoctorShareLedger;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Doctor payout ledger')] class extends Component
{
    use WithPagination;

    public string $doctorId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatingDoctorId(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function doctors()
    {
        return Doctor::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function ledgers()
    {
        $from = \Carbon\Carbon::parse($this->dateFrom)->startOfDay();
        $to = \Carbon\Carbon::parse($this->dateTo)->endOfDay();

        return DoctorShareLedger::query()
            ->with(['doctor', 'paidBy'])
            ->when(filled($this->doctorId), fn ($q) => $q->where('doctor_id', (int) $this->doctorId))
            ->whereBetween('paid_at', [$from, $to])
            ->orderByDesc('paid_at')
            ->paginate(15);
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
                    {{ __('Doctor payout ledger') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Recorded payout batches. Open a row for line-level invoice services.') }}
                </flux:text>
            </div>
            <div class="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:grid-cols-4 lg:items-end">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Doctor') }}</flux:label>
                    <flux:select wire:model.live="doctorId" placeholder="{{ __('All doctors') }}">
                        <flux:select.option value="">{{ __('All doctors') }}</flux:select.option>
                        @foreach ($this->doctors as $doc)
                            <flux:select.option value="{{ $doc->id }}" wire:key="doc-{{ $doc->id }}">{{ $doc->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
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
        @if ($this->ledgers->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No ledger entries in this range.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Paid at') }}</th>
                            <th class="px-6 py-3">{{ __('Doctor') }}</th>
                            <th class="px-6 py-3">{{ __('Period') }}</th>
                            <th class="px-6 py-3">{{ __('Paid by') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Total') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->ledgers as $row)
                            <tr wire:key="led-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $row->paid_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-3 font-medium text-zinc-900 dark:text-white">{{ $row->doctor?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $row->period_from?->format('M j, Y') }} — {{ $row->period_to?->format('M j, Y') }}
                                </td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $row->paidBy?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-end tabular-nums font-semibold text-amber-900 dark:text-amber-200">{{ $this->formatMoney((int) $row->total_share) }}</td>
                                <td class="px-6 py-3 text-end">
                                    <flux:button size="sm" variant="ghost" :href="route('finance.ledger.show', $row)" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($this->ledgers->hasPages())
                <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    {{ $this->ledgers->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
