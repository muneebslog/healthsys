<?php

namespace App\Services;

use App\Models\DoctorShareLedger;
use App\Models\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final class DoctorSharePayoutReceiptBuilder
{
    /**
     * Build a thermal receipt for a doctor share payout ledger row.
     *
     * Full vs base slips follow the same slip-day ordering as {@see DoctorShareCalculator::amountForLine()}.
     */
    public static function fromLedger(DoctorShareLedger $ledger): DoctorSharePayoutReceiptData
    {
        $ledger->loadMissing([
            'doctor',
            'paidBy:id,name',
            'items',
        ]);

        $invoiceServiceIds = $ledger->items->pluck('invoice_service_id')->filter()->unique()->values();
        $invoiceServices = InvoiceService::query()
            ->whereKey($invoiceServiceIds)
            ->with([
                'invoice:id,created_at',
                'servicePrice:id,doctor_share',
            ])
            ->get()
            ->keyBy('id');

        $doctor = $ledger->doctor;
        $tz = config('app.timezone', 'UTC');

        $dates = $invoiceServices
            ->map(fn (InvoiceService $is): string => Carbon::parse($is->invoice->created_at)->timezone($tz)->toDateString())
            ->unique()
            ->values();

        $slipIndexByInvoiceServiceId = [];
        foreach ($dates as $date) {
            $orderedIds = InvoiceService::query()
                ->join('invoices', 'invoices.id', '=', 'invoice_services.invoice_id')
                ->where('invoice_services.doctor_id', $doctor->id)
                ->whereDate('invoices.created_at', $date)
                ->orderBy('invoices.created_at')
                ->orderBy('invoice_services.id')
                ->pluck('invoice_services.id');

            foreach ($orderedIds as $index => $id) {
                $slipIndexByInvoiceServiceId[(int) $id] = (int) $index;
            }
        }

        $fullShareSlipCount = 0;
        $baseShareSlipCount = 0;
        $fullShareSubtotal = 0;
        $baseShareSubtotal = 0;
        $baseChargedSum = 0;
        $fullInvoiceIds = [];
        $baseInvoiceIds = [];

        foreach ($invoiceServiceIds as $invoiceServiceId) {
            $is = $invoiceServices->get($invoiceServiceId);
            if (! $is || ! $is->invoice) {
                continue;
            }

            $slipIndex = $slipIndexByInvoiceServiceId[$is->id] ?? 0;
            $isFullShare = $doctor->first_five_slips_full_share && $slipIndex < 5;
            $amount = (int) $is->doctor_share_amount;
            $invoiceId = (int) $is->invoice_id;

            if ($isFullShare) {
                $fullShareSlipCount++;
                $fullShareSubtotal += $amount;
                $fullInvoiceIds[$invoiceId] = true;
            } else {
                $baseShareSlipCount++;
                $baseShareSubtotal += $amount;
                $baseChargedSum += (int) $is->final_amount;
                $baseInvoiceIds[$invoiceId] = true;
            }
        }

        $totalShare = $fullShareSubtotal + $baseShareSubtotal;

        $avgFull = $fullShareSlipCount > 0 ? (int) round($fullShareSubtotal / $fullShareSlipCount) : null;
        $avgBase = $baseShareSlipCount > 0 ? (int) round($baseShareSubtotal / $baseShareSlipCount) : null;
        $basePct = $baseChargedSum > 0 ? (int) round(100 * $baseShareSubtotal / $baseChargedSum) : null;

        $paidAt = CarbonImmutable::parse($ledger->paid_at)->timezone($tz);

        return new DoctorSharePayoutReceiptData(
            doctorName: $doctor->name,
            paidAt: $paidAt,
            periodFrom: $ledger->period_from->toDateString(),
            periodTo: $ledger->period_to->toDateString(),
            fullShareSlipCount: $fullShareSlipCount,
            baseShareSlipCount: $baseShareSlipCount,
            fullShareDistinctInvoiceCount: count($fullInvoiceIds),
            baseShareDistinctInvoiceCount: count($baseInvoiceIds),
            fullShareSubtotal: $fullShareSubtotal,
            baseShareSubtotal: $baseShareSubtotal,
            totalShare: $totalShare,
            avgFullSharePerSlip: $avgFull,
            avgBaseSharePerSlip: $avgBase,
            baseSharePercentOfLine: $basePct,
            ledgerId: $ledger->id,
            paidByName: $ledger->paidBy?->name,
            notes: $ledger->notes,
        );
    }
}
