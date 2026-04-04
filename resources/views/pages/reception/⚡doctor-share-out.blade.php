<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\DoctorShareLedger;
use App\Models\DoctorShareLedgerItem;
use App\Models\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Doctor share out')] class extends Component
{
    public string $doctorId = '';

    /** today | 7d | 15d | custom */
    public string $period = 'today';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $payNotes = '';

    public bool $showPayModal = false;

    public bool $isReceptionPayout = false;

    /**
     * Finance managers audit unpaid lines and history; they cannot record payouts.
     */
    public bool $isFinanceAuditOnly = false;

    public function mount(): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin, UserRole::FinanceManager], true)) {
            abort(403);
        }

        $this->isReceptionPayout = $role === UserRole::Staff;
        $this->isFinanceAuditOnly = $role === UserRole::FinanceManager;

        if ($this->isReceptionPayout) {
            $this->period = 'today';
        }

        $this->syncDatesFromPeriod();
    }

    public function updatedPeriod(string $value): void
    {
        if ($this->isReceptionPayout) {
            $this->period = 'today';
        }

        if ($value !== 'custom') {
            $this->syncDatesFromPeriod();
        }
    }

    protected function syncDatesFromPeriod(): void
    {
        match ($this->period) {
            'today' => [
                $this->dateFrom = now()->toDateString(),
                $this->dateTo = now()->toDateString(),
            ],
            '7d' => [
                $this->dateFrom = now()->subDays(6)->toDateString(),
                $this->dateTo = now()->toDateString(),
            ],
            '15d' => [
                $this->dateFrom = now()->subDays(14)->toDateString(),
                $this->dateTo = now()->toDateString(),
            ],
            default => null,
        };
    }

    #[Computed]
    public function doctors()
    {
        return Doctor::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function unpaidLines()
    {
        if ($this->doctorId === '' || $this->doctorId === '0') {
            return collect();
        }

        if ($this->dateFrom === '' || $this->dateTo === '') {
            return collect();
        }

        return $this->unpaidShareQuery()->get();
    }

    #[Computed]
    public function summaryByService()
    {
        return $this->unpaidLines
            ->groupBy('service_name')
            ->map(function ($rows, $name) {
                $sum = (int) $rows->sum('doctor_share_amount');

                return [
                    'service_name' => $name,
                    'count' => $rows->count(),
                    'subtotal' => $sum,
                ];
            })
            ->values();
    }

    #[Computed]
    public function grandTotal(): int
    {
        return (int) $this->unpaidLines->sum('doctor_share_amount');
    }

    #[Computed]
    public function recentLedger()
    {
        if ($this->doctorId === '' || $this->doctorId === '0') {
            return collect();
        }

        return DoctorShareLedger::query()
            ->where('doctor_id', (int) $this->doctorId)
            ->with('paidBy:id,name')
            ->orderByDesc('paid_at')
            ->limit(6)
            ->get();
    }

    protected function unpaidShareQuery()
    {
        $doctorId = (int) $this->doctorId;

        return InvoiceService::query()
            ->unpaidDoctorShare()
            ->where('invoice_services.doctor_id', $doctorId)
            ->select([
                'invoice_services.id',
                'invoice_services.service_id',
                'invoice_services.price',
                'invoice_services.doctor_share_amount',
                'invoice_services.final_amount',
                'patients.name as patient_name',
                'queue_tokens.token_number',
                'invoices.created_at as invoice_created_at',
                'services.name as service_name',
            ])
            ->join('invoices', 'invoices.id', '=', 'invoice_services.invoice_id')
            ->join('patients', 'patients.id', '=', 'invoices.patient_id')
            ->join('services', 'services.id', '=', 'invoice_services.service_id')
            ->leftJoin('visit_services', function ($join) {
                $join->on('visit_services.visit_id', '=', 'invoices.visit_id')
                    ->on('visit_services.service_id', '=', 'invoice_services.service_id')
                    ->on('visit_services.service_price_id', '=', 'invoice_services.service_price_id')
                    ->whereColumn('visit_services.doctor_id', 'invoice_services.doctor_id');
            })
            ->leftJoin('queue_tokens', 'queue_tokens.id', '=', 'visit_services.queue_token_id')
            ->whereDate('invoices.created_at', '>=', $this->dateFrom)
            ->whereDate('invoices.created_at', '<=', $this->dateTo)
            ->orderBy('invoices.created_at')
            ->orderBy('invoice_services.id');
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }

    public function confirmPay(): void
    {
        if ($this->isFinanceAuditOnly) {
            return;
        }

        $this->resetErrorBag('payout');

        $this->validate([
            'doctorId' => ['required', 'exists:doctors,id'],
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ], [], [
            'doctorId' => __('doctor'),
            'dateFrom' => __('from'),
            'dateTo' => __('to'),
        ]);

        if ($this->isReceptionPayout && ($this->dateFrom !== now()->toDateString() || $this->dateTo !== now()->toDateString())) {
            $this->addError('payout', __('Reception can only pay today’s doctor share (daily payout).'));

            return;
        }

        if ($this->unpaidLines->isEmpty()) {
            $this->addError('payout', __('No unpaid doctor share in this period.'));

            return;
        }

        $this->showPayModal = true;
    }

    public function logAndPay(): void
    {
        if ($this->isFinanceAuditOnly) {
            return;
        }

        $this->validate([
            'doctorId' => ['required', 'exists:doctors,id'],
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
            'payNotes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'doctorId' => __('doctor'),
            'dateFrom' => __('from'),
            'dateTo' => __('to'),
        ]);

        if ($this->isReceptionPayout && ($this->dateFrom !== now()->toDateString() || $this->dateTo !== now()->toDateString())) {
            $this->addError('payout', __('Reception can only pay today’s doctor share (daily payout).'));

            return;
        }

        $doctorId = (int) $this->doctorId;
        $from = $this->dateFrom;
        $to = $this->dateTo;

        $newLedgerId = null;

        try {
            $newLedgerId = DB::transaction(function () use ($doctorId, $from, $to): int {
                $ids = DB::table('invoice_services')
                    ->join('invoices', 'invoices.id', '=', 'invoice_services.invoice_id')
                    ->where('invoice_services.doctor_id', $doctorId)
                    ->where('invoice_services.doctor_share_paid', false)
                    ->where('invoice_services.doctor_share_amount', '>', 0)
                    ->whereNotNull('invoice_services.doctor_id')
                    ->whereDate('invoices.created_at', '>=', $from)
                    ->whereDate('invoices.created_at', '<=', $to)
                    ->orderBy('invoice_services.id')
                    ->lockForUpdate()
                    ->pluck('invoice_services.id');

                if ($ids->isEmpty()) {
                    throw new \RuntimeException('empty');
                }

                $total = (int) InvoiceService::query()->whereKey($ids)->sum('doctor_share_amount');

                $ledger = DoctorShareLedger::query()->create([
                    'doctor_id' => $doctorId,
                    'paid_by' => Auth::id(),
                    'period_from' => $from,
                    'period_to' => $to,
                    'total_share' => $total,
                    'paid_at' => now(),
                    'notes' => $this->payNotes !== '' ? $this->payNotes : null,
                ]);

                foreach ($ids as $invoiceServiceId) {
                    DoctorShareLedgerItem::query()->create([
                        'ledger_id' => $ledger->id,
                        'invoice_service_id' => $invoiceServiceId,
                    ]);
                }

                InvoiceService::query()->whereKey($ids)->update(['doctor_share_paid' => true]);

                return $ledger->id;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'empty') {
                $this->addError('payout', __('Nothing left to settle. Refresh and try again.'));

                return;
            }
            throw $e;
        }

        $this->showPayModal = false;
        $this->payNotes = '';
        unset($this->unpaidLines, $this->summaryByService, $this->grandTotal, $this->recentLedger);

        $printUrl = route('reception.doctor-share-payout-receipt', ['ledger' => $newLedgerId], absolute: true);
        $this->js('setTimeout(function(){ window.open('.Js::from($printUrl).', "_blank", "noopener,noreferrer"); }, 100)');
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    {{-- Ledger / payout desk: amber ledger accent on cool zinc (distinct from shift emerald) --}}
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-amber-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-amber-950/20">
        <div class="pointer-events-none absolute -end-20 -top-20 size-56 rounded-full bg-amber-400/10 blur-3xl dark:bg-amber-500/10"></div>
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Doctor share out') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Review unpaid doctor shares from invoices, then log cash paid out. This is separate from patient invoice payment — it settles what the clinic owes the doctor.') }}
                </flux:text>
            </div>
            @if ($isFinanceAuditOnly)
                <flux:badge color="violet" class="shrink-0">{{ __('Finance audit') }}</flux:badge>
            @else
                <flux:badge color="amber" class="shrink-0">{{ __('Reception') }}</flux:badge>
            @endif
        </div>
    </header>

    @if ($isFinanceAuditOnly)
        <flux:callout icon="information-circle" color="violet">
            {{ __('You can review unpaid shares and payout history. Recording payouts is limited to reception and admin.') }}
        </flux:callout>
    @endif

    <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-6 lg:grid-cols-12 lg:items-end">
            <flux:field class="lg:col-span-4">
                <flux:label>{{ __('Doctor') }}</flux:label>
                <flux:select wire:model.live="doctorId" placeholder="{{ __('Choose a doctor…') }}">
                    <flux:select.option value="">{{ __('Choose a doctor…') }}</flux:select.option>
                    @foreach ($this->doctors as $doc)
                        <flux:select.option value="{{ $doc->id }}" wire:key="doc-opt-{{ $doc->id }}">{{ $doc->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="doctorId" />
            </flux:field>

            <flux:field class="lg:col-span-4">
                <flux:label>{{ __('Duration') }}</flux:label>
                <flux:select wire:model.live="period">
                    <flux:select.option value="today">{{ __('Today') }}</flux:select.option>
                    @if (! $isReceptionPayout)
                        <flux:select.option value="7d">{{ __('Last 7 days') }}</flux:select.option>
                        <flux:select.option value="15d">{{ __('Last 15 days') }}</flux:select.option>
                        <flux:select.option value="custom">{{ __('Custom range') }}</flux:select.option>
                    @endif
                </flux:select>
            </flux:field>

            @if ($period === 'custom')
                <flux:field class="lg:col-span-2">
                    <flux:label>{{ __('From') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateFrom" />
                    <flux:error name="dateFrom" />
                </flux:field>
                <flux:field class="lg:col-span-2">
                    <flux:label>{{ __('To') }}</flux:label>
                    <flux:input type="date" wire:model.live="dateTo" />
                    <flux:error name="dateTo" />
                </flux:field>
            @else
                <div class="lg:col-span-4 flex flex-col gap-1 rounded-xl border border-dashed border-zinc-200 bg-zinc-50/50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/30">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Period') }}</flux:text>
                    <flux:text class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                        {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M j, Y') }}
                        —
                        {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M j, Y') }}
                    </flux:text>
                </div>
            @endif
        </div>
    </section>

    <flux:error name="payout" />

    @if ($doctorId === '')
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/40 px-8 py-16 text-center dark:border-zinc-600 dark:bg-zinc-900/30">
            <flux:heading size="lg" class="mb-2 text-zinc-800 dark:text-zinc-200">{{ __('Select a doctor') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('Choose who you are paying out to see unpaid share lines and totals.') }}
            </flux:text>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-5">
            <div class="space-y-6 lg:col-span-2">
                <flux:heading size="lg">{{ __('Summary by service') }}</flux:heading>
                @if ($this->summaryByService->isEmpty())
                    <flux:card class="border-zinc-200 dark:border-zinc-700">
                        <flux:text class="text-zinc-500">{{ __('No unpaid shares in this period.') }}</flux:text>
                    </flux:card>
                @else
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                        <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            <thead class="bg-zinc-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Service') }}</th>
                                    <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Count') }}</th>
                                    <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Subtotal') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($this->summaryByService as $row)
                                    <tr wire:key="sum-{{ $row['service_name'] }}">
                                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $row['service_name'] }}</td>
                                        <td class="px-4 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row['count'] }}</td>
                                        <td class="px-4 py-3 text-end tabular-nums font-medium text-amber-900 dark:text-amber-300">{{ $this->formatMoney($row['subtotal']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-amber-200/80 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20">
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-end font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Grand total') }}</td>
                                    <td class="px-4 py-3 text-end text-lg font-bold tabular-nums text-amber-900 dark:text-amber-200">{{ $this->formatMoney($this->grandTotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

                @if (! $isFinanceAuditOnly)
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <flux:button
                            variant="primary"
                            icon="currency-dollar"
                            wire:click="confirmPay"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="confirmPay">{{ __('Log & pay') }}</span>
                            <span wire:loading wire:target="confirmPay">{{ __('Checking…') }}</span>
                        </flux:button>
                        <flux:text class="text-sm text-zinc-500">
                            {{ __('Marks every unpaid line in the table below as settled.') }}
                        </flux:text>
                    </div>
                @endif
            </div>

            <div class="space-y-6 lg:col-span-3">
                <flux:heading size="lg">{{ __('Details') }}</flux:heading>
                @if ($this->unpaidLines->isEmpty())
                    <flux:card class="border-zinc-200 dark:border-zinc-700">
                        <flux:text class="text-zinc-500">{{ __('Nothing to show for this filter.') }}</flux:text>
                    </flux:card>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                        <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            <thead class="bg-zinc-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="whitespace-nowrap px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Token') }}</th>
                                    <th class="px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Patient') }}</th>
                                    <th class="px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Service') }}</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Full price') }}</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Doc share') }}</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Time') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($this->unpaidLines as $line)
                                    <tr wire:key="line-{{ $line->id }}">
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-zinc-700 dark:text-zinc-300">
                                            @if ($line->token_number)
                                                {{ __('T-:n', ['n' => $line->token_number]) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-zinc-900 dark:text-white">{{ $line->patient_name }}</td>
                                        <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-400">{{ $line->service_name }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-end tabular-nums text-zinc-700 dark:text-zinc-300">{{ $this->formatMoney((int) $line->final_amount) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-end tabular-nums font-medium text-amber-800 dark:text-amber-300">{{ $this->formatMoney((int) $line->doctor_share_amount) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-end text-zinc-500 dark:text-zinc-500">
                                            {{ \Illuminate\Support\Carbon::parse($line->invoice_created_at)->timezone(config('app.timezone'))->format('g:i A') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($this->recentLedger->isNotEmpty())
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50/30 p-5 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:heading size="md" class="mb-3">{{ __('Recent payouts (this doctor)') }}</flux:heading>
                        <ul class="space-y-2 text-sm">
                            @foreach ($this->recentLedger as $entry)
                                <li wire:key="led-{{ $entry->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200/80 pb-2 last:border-0 dark:border-zinc-700">
                                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                                        {{ $entry->paid_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                        @if ($entry->paidBy)
                                            <span class="text-zinc-500">· {{ $entry->paidBy->name }}</span>
                                        @endif
                                    </flux:text>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:text class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ $this->formatMoney((int) $entry->total_share) }}</flux:text>
                                        <flux:button
                                            href="{{ route('reception.doctor-share-payout-receipt', $entry) }}"
                                            variant="ghost"
                                            size="sm"
                                            icon="printer"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {{ __('Receipt') }}
                                        </flux:button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <flux:modal wire:model="showPayModal" name="confirm-doc-payout" class="min-w-[22rem] max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Confirm payout') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('This will record a payout and mark all listed invoice lines as paid to the doctor for this period.') }}
            </flux:text>
            @if ($doctorId !== '')
                @php($doc = $this->doctors->firstWhere('id', (int) $doctorId))
                <div class="rounded-xl bg-amber-50/80 p-4 text-sm dark:bg-amber-950/30">
                    <div class="flex justify-between gap-4">
                        <span class="text-zinc-500">{{ __('Doctor') }}</span>
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $doc?->name }}</span>
                    </div>
                    <div class="mt-2 flex justify-between gap-4">
                        <span class="text-zinc-500">{{ __('Amount') }}</span>
                        <span class="font-bold tabular-nums text-amber-900 dark:text-amber-200">{{ $this->formatMoney($this->grandTotal) }}</span>
                    </div>
                </div>
            @endif
            <flux:field>
                <flux:label>{{ __('Notes (optional)') }}</flux:label>
                <flux:textarea wire:model="payNotes" rows="2" placeholder="{{ __('e.g. Cash handed in office, receipt #…') }}" />
                <flux:error name="payNotes" />
            </flux:field>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="$set('showPayModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" icon="check" wire:click="logAndPay" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="logAndPay">{{ __('Confirm log & pay') }}</span>
                    <span wire:loading wire:target="logAndPay">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
