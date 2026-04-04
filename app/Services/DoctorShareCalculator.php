<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\InvoiceService;
use App\Models\ServicePrice;
use Illuminate\Support\Carbon;

class DoctorShareCalculator
{
    /**
     * Number of invoice lines (slips) for this doctor on the current calendar day (app timezone).
     */
    public static function countSlipsTodayForDoctor(int $doctorId): int
    {
        return (int) InvoiceService::query()
            ->join('invoices', 'invoice_services.invoice_id', '=', 'invoices.id')
            ->where('invoice_services.doctor_id', $doctorId)
            ->whereDate('invoices.created_at', now()->toDateString())
            ->count();
    }

    /**
     * 0-based slip index for this line among the doctor’s lines on the invoice’s calendar day.
     *
     * Ordering matches checkout: earlier invoice time, then lower invoice id (same timestamp), then lower line id within the invoice.
     */
    public static function slipIndexOnDoctorCalendarDay(InvoiceService $is): int
    {
        $is->loadMissing('invoice');
        $invoice = $is->invoice;
        if (! $invoice || ! $is->doctor_id) {
            return 0;
        }

        $invoiceCreatedAt = $invoice->created_at;
        if (! $invoiceCreatedAt instanceof Carbon) {
            $invoiceCreatedAt = Carbon::parse($invoiceCreatedAt);
        }

        $day = $invoiceCreatedAt->toDateString();

        return (int) InvoiceService::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_services.invoice_id')
            ->where('invoice_services.doctor_id', $is->doctor_id)
            ->whereDate('invoices.created_at', $day)
            ->where(function ($q) use ($invoice, $is, $invoiceCreatedAt): void {
                $q->where('invoices.created_at', '<', $invoiceCreatedAt)
                    ->orWhere(function ($inner) use ($invoice, $invoiceCreatedAt): void {
                        $inner->where('invoices.created_at', $invoiceCreatedAt)
                            ->where('invoices.id', '<', $invoice->id);
                    })
                    ->orWhere(function ($inner) use ($invoice, $is): void {
                        $inner->where('invoice_services.invoice_id', $invoice->id)
                            ->where('invoice_services.id', '<', $is->id);
                    });
            })
            ->count();
    }

    /**
     * Doctor share for a persisted invoice line using current service price and doctor rules (e.g. first-five full share).
     */
    public static function reconciledDoctorShareAmount(InvoiceService $is): int
    {
        $is->loadMissing(['invoice', 'servicePrice.doctor']);
        $sp = $is->servicePrice;
        if (! $sp || ! $sp->doctor_id) {
            return (int) $is->doctor_share_amount;
        }

        $charged = (int) $is->final_amount;
        $idx = self::slipIndexOnDoctorCalendarDay($is);

        return self::amountForLine($sp, $charged, $idx);
    }

    /**
     * Doctor share in minor units for one invoice line.
     *
     * When the doctor has "first five slips full share", the first five slips of the day use 100% of the
     * line amount; further slips use the configured service doctor_share percentage.
     *
     * @param  int  $slipIndexTodayZeroBased  0-based index among today’s slips for this doctor (first slip = 0).
     */
    public static function amountForLine(ServicePrice $sp, int $chargedAmount, int $slipIndexTodayZeroBased): int
    {
        if (! $sp->doctor_id) {
            return 0;
        }

        $assignedShare = (int) round($chargedAmount * $sp->doctor_share / 100);

        $doctor = $sp->relationLoaded('doctor')
            ? $sp->getRelation('doctor')
            : Doctor::query()->find($sp->doctor_id);

        if (! $doctor || ! $doctor->first_five_slips_full_share) {
            return $assignedShare;
        }

        if ($slipIndexTodayZeroBased < 5) {
            return $chargedAmount;
        }

        return $assignedShare;
    }
}
