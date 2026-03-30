<?php

use App\Livewire\Concerns\GuardsDoctorAccess;
use App\Models\Doctor;
use App\Models\DoctorShareLedger;
use App\Models\InvoiceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My payouts')] class extends Component
{
    use GuardsDoctorAccess;

    public Doctor $doctor;

    public function mount(): void
    {
        $this->doctor = $this->doctorProfile();
    }

    #[Computed]
    public function unpaidLines()
    {
        return $this->unpaidShareQuery()->get();
    }

    #[Computed]
    public function summaryByService()
    {
        return $this->unpaidLines
            ->groupBy('service_name')
            ->map(function ($rows, $name) {
                return [
                    'service_name' => $name,
                    'count' => $rows->count(),
                    'subtotal' => (int) $rows->sum('doctor_share_amount'),
                ];
            })
            ->values();
    }

    #[Computed]
    public function pendingTotal(): int
    {
        return (int) $this->unpaidLines->sum('doctor_share_amount');
    }

    #[Computed]
    public function payoutHistory()
    {
        return DoctorShareLedger::query()
            ->where('doctor_id', $this->doctor->id)
            ->with('paidBy:id,name')
            ->orderByDesc('paid_at')
            ->limit(100)
            ->get();
    }

    protected function unpaidShareQuery()
    {
        $doctorId = $this->doctor->id;

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
            ->orderBy('invoices.created_at')
            ->orderBy('invoice_services.id');
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-teal-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-teal-950/25">
        <div class="pointer-events-none absolute -end-20 -top-20 size-56 rounded-full bg-teal-400/10 blur-3xl dark:bg-teal-500/10"></div>
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Payouts') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Amounts the clinic still owes you from paid invoices, and a history of payouts you have already received.') }}
                </flux:text>
            </div>
            <flux:badge color="teal" class="shrink-0">{{ __('Doctor') }}</flux:badge>
        </div>
    </header>

    <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-2">{{ __('Current — not yet paid out') }}</flux:heading>
        <flux:text class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('These invoice lines are settled with patients but your share has not been logged as paid by reception yet.') }}
        </flux:text>

        @if ($this->unpaidLines->isEmpty())
            <flux:card class="border-zinc-200 dark:border-zinc-700">
                <flux:text class="text-zinc-500">{{ __('You have no outstanding share at the moment.') }}</flux:text>
            </flux:card>
        @else
            <div class="grid gap-8 lg:grid-cols-5">
                <div class="space-y-4 lg:col-span-2">
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
                                        <td class="px-4 py-3 text-end tabular-nums font-medium text-teal-900 dark:text-teal-300">{{ $this->formatMoney($row['subtotal']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-teal-200/80 bg-teal-50/50 dark:border-teal-900/40 dark:bg-teal-950/20">
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-end font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Outstanding total') }}</td>
                                    <td class="px-4 py-3 text-end text-lg font-bold tabular-nums text-teal-900 dark:text-teal-200">{{ $this->formatMoney($this->pendingTotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="lg:col-span-3">
                    <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                        <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            <thead class="bg-zinc-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="whitespace-nowrap px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Token') }}</th>
                                    <th class="px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Patient') }}</th>
                                    <th class="px-3 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Service') }}</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Doc share') }}</th>
                                    <th class="whitespace-nowrap px-3 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Date') }}</th>
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
                                        <td class="whitespace-nowrap px-3 py-2.5 text-end tabular-nums font-medium text-teal-800 dark:text-teal-300">{{ $this->formatMoney((int) $line->doctor_share_amount) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-end text-zinc-500">
                                            {{ \Illuminate\Support\Carbon::parse($line->invoice_created_at)->timezone(config('app.timezone'))->format('M j, Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-2">{{ __('Previous payouts') }}</flux:heading>
        <flux:text class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Recorded when reception logs cash paid to you.') }}
        </flux:text>

        @if ($this->payoutHistory->isEmpty())
            <flux:card class="border-zinc-200 dark:border-zinc-700">
                <flux:text class="text-zinc-500">{{ __('No payouts logged yet.') }}</flux:text>
            </flux:card>
        @else
            <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50/80 dark:bg-zinc-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Paid at') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Period') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Logged by') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->payoutHistory as $entry)
                            <tr wire:key="led-{{ $entry->id }}">
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    {{ $entry->paid_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $entry->period_from->format('M j, Y') }}
                                    —
                                    {{ $entry->period_to->format('M j, Y') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums font-semibold text-zinc-900 dark:text-white">
                                    {{ $this->formatMoney((int) $entry->total_share) }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $entry->paidBy?->name ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
