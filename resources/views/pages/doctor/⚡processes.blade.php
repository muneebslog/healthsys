<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\GuardsDoctorAccess;
use App\Models\Doctor;
use App\Models\Procedure;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Processes')] class extends Component
{
    use GuardsDoctorAccess;

    public Doctor $doctor;

    /** @var 'this_week'|'last_week'|'custom' */
    public string $rangePreset = 'this_week';

    public string $customFrom = '';

    public string $customTo = '';

    public function mount(): void
    {
        $this->doctor = $this->doctorProfile();
        $this->syncPresetToCustomFields();
    }

    public function updatedRangePreset(): void
    {
        if ($this->rangePreset !== 'custom') {
            $this->syncPresetToCustomFields();
        }
        unset($this->filteredProcedures, $this->rangeSummary);
    }

    public function applyCustomRange(): void
    {
        $this->validate([
            'customFrom' => ['required', 'date'],
            'customTo' => ['required', 'date', 'after_or_equal:customFrom'],
        ], [], [
            'customFrom' => __('from'),
            'customTo' => __('to'),
        ]);

        $this->rangePreset = 'custom';
        unset($this->filteredProcedures, $this->rangeSummary);
    }

    protected function syncPresetToCustomFields(): void
    {
        if ($this->rangePreset === 'custom') {
            return;
        }

        [$from, $to] = $this->resolvePresetBoundsFor($this->rangePreset);

        $this->customFrom = $from->toDateString();
        $this->customTo = $to->toDateString();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function resolvePresetBoundsFor(string $preset): array
    {
        $tz = config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($tz);

        return match ($preset) {
            'this_week' => [$now->startOfWeek(), $now->endOfWeek()],
            'last_week' => (function () use ($now) {
                $anchor = $now->subWeek();

                return [$anchor->startOfWeek(), $anchor->endOfWeek()];
            })(),
            default => [$now->startOfWeek(), $now->endOfWeek()],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function activeRange(): array
    {
        if ($this->rangePreset === 'custom' && $this->customFrom !== '' && $this->customTo !== '') {
            $tz = config('app.timezone', 'UTC');

            return [
                CarbonImmutable::parse($this->customFrom, $tz)->startOfDay(),
                CarbonImmutable::parse($this->customTo, $tz)->endOfDay(),
            ];
        }

        if ($this->rangePreset === 'custom') {
            return $this->resolvePresetBoundsFor('this_week');
        }

        return $this->resolvePresetBoundsFor($this->rangePreset);
    }

    #[Computed]
    public function rangeSummary(): string
    {
        [$from, $to] = $this->activeRange();

        return $from->format('M j, Y').' — '.$to->format('M j, Y');
    }

    #[Computed]
    public function filteredProcedures()
    {
        [$from, $to] = $this->activeRange();
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        return Procedure::query()
            ->where('doctor_id', $this->doctor->id)
            ->with(['patient:id,name'])
            ->withSum([
                'invoices' => fn ($q) => $q->where('status', InvoiceStatus::Paid),
            ], 'final_amount')
            ->whereRaw('COALESCE(procedures.procedure_date, date(procedures.created_at)) >= ?', [$fromStr])
            ->whereRaw('COALESCE(procedures.procedure_date, date(procedures.created_at)) <= ?', [$toStr])
            ->orderByRaw('COALESCE(procedures.procedure_date, date(procedures.created_at)) DESC')
            ->orderByDesc('procedures.id')
            ->get();
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-cyan-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-950 dark:to-cyan-950/20">
        <div class="pointer-events-none absolute -end-20 -top-20 size-56 rounded-full bg-cyan-400/10 blur-3xl dark:bg-cyan-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Processes') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Your procedures with package totals and payments. Filter by week or a custom date range (uses procedure date when set, otherwise record date).') }}
                </flux:text>
            </div>
            <flux:badge color="cyan" class="shrink-0">{{ __('OT') }}</flux:badge>
        </div>
    </header>

    <flux:card class="space-y-6 p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <flux:field class="max-w-xs">
                <flux:label>{{ __('Range') }}</flux:label>
                <flux:select wire:model.live="rangePreset">
                    <flux:select.option value="this_week">{{ __('This week') }}</flux:select.option>
                    <flux:select.option value="last_week">{{ __('Last week') }}</flux:select.option>
                    <flux:select.option value="custom">{{ __('Custom range') }}</flux:select.option>
                </flux:select>
            </flux:field>

            @if ($rangePreset === 'custom')
                <form wire:submit="applyCustomRange" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <flux:field>
                        <flux:label>{{ __('From') }}</flux:label>
                        <flux:input type="date" wire:model="customFrom" />
                        <flux:error name="customFrom" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('To') }}</flux:label>
                        <flux:input type="date" wire:model="customTo" />
                        <flux:error name="customTo" />
                    </flux:field>
                    <flux:button type="submit" variant="primary">{{ __('Apply') }}</flux:button>
                </form>
            @else
                <flux:text class="text-sm text-zinc-500">
                    {{ $this->rangeSummary }}
                </flux:text>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] text-left text-sm">
                <thead class="border-b border-zinc-100 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800">
                    <tr>
                        <th class="px-3 py-3">{{ __('Ref') }}</th>
                        <th class="px-3 py-3">{{ __('Patient') }}</th>
                        <th class="px-3 py-3">{{ __('Operation') }}</th>
                        <th class="px-3 py-3">{{ __('Date') }}</th>
                        <th class="px-3 py-3">{{ __('Room') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Package') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Paid') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->filteredProcedures as $row)
                        @php($paid = (int) ($row->invoices_sum_final_amount ?? 0))
                        @php($bal = (int) $row->package_price - $paid)
                        @php($effDate = $row->procedure_date ?? $row->created_at)
                        <tr wire:key="doc-proc-{{ $row->id }}">
                            <td class="px-3 py-3 font-medium text-zinc-900 dark:text-white">{{ $row->reference_number }}</td>
                            <td class="px-3 py-3 text-zinc-700 dark:text-zinc-300">{{ $row->patient?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-zinc-700 dark:text-zinc-300">{{ $row->operation_name }}</td>
                            <td class="px-3 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ $effDate->format('M j, Y') }}
                            </td>
                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-400">{{ $row->room_number ?: '—' }}</td>
                            <td class="px-3 py-3 text-end tabular-nums">{{ $this->formatMoney((int) $row->package_price) }}</td>
                            <td class="px-3 py-3 text-end tabular-nums text-teal-700 dark:text-teal-300">{{ $this->formatMoney($paid) }}</td>
                            <td class="px-3 py-3 text-end tabular-nums font-medium {{ $bal !== 0 ? 'text-amber-800 dark:text-amber-200' : 'text-zinc-600' }}">
                                {{ $this->formatMoney($bal) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-12 text-center text-zinc-500">{{ __('No procedures in this range.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
