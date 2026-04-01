<?php

use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Invoices')] class extends Component
{
    use WithPagination;

    public string $search = '';

    /**
     * Empty string = all statuses (easier to bind to Flux select).
     */
    public string $status = '';

    public bool $showAllShifts = false;

    public function mount(): void
    {
        if (config('hms.skip_role_page_guards')) {
            return;
        }

        $role = Auth::user()?->role;

        if (! in_array($role, [UserRole::Staff, UserRole::Admin, UserRole::Owner], true)) {
            abort(403);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingShowAllShifts(): void
    {
        $this->resetPage();
    }

    public function canPrint(): bool
    {
        if (config('hms.skip_role_page_guards')) {
            return true;
        }

        $role = Auth::user()?->role;

        return in_array($role, [UserRole::Staff, UserRole::Admin], true);
    }

    public function statusBadgeColor(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Paid => 'lime',
            InvoiceStatus::Draft => 'violet',
            InvoiceStatus::Cancelled => 'rose',
        };
    }

    public function statusBadgeLabel(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Paid => __('Paid'),
            InvoiceStatus::Draft => __('Draft'),
            InvoiceStatus::Cancelled => __('Cancelled'),
        };
    }

    #[Computed]
    public function activeShift(): ?Shift
    {
        return Shift::query()
            ->where('status', ShiftStatus::Open)
            ->first();
    }

    #[Computed]
    public function invoices()
    {
        return Invoice::query()
            ->with(['patient', 'shift'])
            ->when(! $this->showAllShifts, function ($q): void {
                $shiftId = $this->activeShift?->id;

                if (! $shiftId) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->where('shift_id', $shiftId);
            })
            ->when(filled($this->status), function ($q): void {
                $q->where('status', $this->status);
            })
            ->when(filled($this->search), function ($q): void {
                $term = trim($this->search);
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

                $q->where(function ($q) use ($term, $like): void {
                    if (ctype_digit($term)) {
                        $q->orWhere('id', (int) $term);
                    }

                    $q->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate(12);
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-emerald-50/50 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950/20">
        <div class="pointer-events-none absolute -end-16 -top-20 size-56 rounded-full bg-emerald-400/15 blur-3xl dark:bg-emerald-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Invoices') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('All invoices (newest first). Filter by status or search by patient name / #id.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <flux:badge color="zinc" class="shrink-0">
                    {{ __('Total') }}: {{ $this->invoices->total() }}
                </flux:badge>
            </div>
        </div>
    </header>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:items-end">
                <flux:field>
                    <flux:label>{{ __('Search') }}</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        icon="magnifying-glass"
                        placeholder="{{ __('Patient name or #id…') }}"
                    />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model.live="status">
                        <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                        <flux:select.option value="{{ InvoiceStatus::Paid->value }}">{{ __('Paid') }}</flux:select.option>
                        <flux:select.option value="{{ InvoiceStatus::Draft->value }}">{{ __('Draft') }}</flux:select.option>
                        <flux:select.option value="{{ InvoiceStatus::Cancelled->value }}">{{ __('Cancelled') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Shift') }}</flux:label>
                    <div class="flex flex-wrap items-center gap-3">
                        <flux:checkbox wire:model.live="showAllShifts" :label="__('Show all shifts')" />

                        @if (! $this->showAllShifts)
                            @if ($this->activeShift?->opened_at)
                                <flux:badge color="emerald">
                                    {{ __('Current') }}: {{ $this->activeShift->opened_at->timezone(config('app.timezone'))->format('M j, Y') }}
                                </flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('No active shift') }}</flux:badge>
                            @endif
                        @endif
                    </div>
                </flux:field>
            </div>

            <div class="mt-3 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400/90 ring-1 ring-emerald-600/20"></span>
                {{ __('Tip: printing is available for staff/admin.') }}
            </div>
        </div>

        @if ($this->invoices->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No invoices match your filters.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Invoice') }}</th>
                            <th class="px-6 py-3">{{ __('Patient') }}</th>
                            <th class="px-6 py-3">{{ __('Shift') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Total') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Created') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->invoices as $inv)
                            <tr wire:key="inv-row-{{ $inv->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">#{{ $inv->id }}</td>
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">
                                    {{ $inv->patient?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                    @if ($inv->shift?->opened_at)
                                        {{ $inv->shift->opened_at->timezone(config('app.timezone'))->format('M j, Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge size="sm" color="{{ $this->statusBadgeColor($inv->status) }}">
                                        {{ $this->statusBadgeLabel($inv->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 text-end tabular-nums font-semibold text-zinc-900 dark:text-white">
                                    {{ $this->formatMoney((int) $inv->final_amount) }}
                                </td>
                                <td class="px-6 py-4 text-end text-zinc-600 dark:text-zinc-400">
                                    {{ $inv->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-6 py-4 text-end">
                                    @if ($this->canPrint())
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            :href="route('invoices.print', $inv)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {{ __('Print') }}
                                        </flux:button>
                                    @else
                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">—</flux:text>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->invoices->hasPages())
                <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    {{ $this->invoices->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

