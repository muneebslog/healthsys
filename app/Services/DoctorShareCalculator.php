<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\InvoiceService;
use App\Models\ServicePrice;

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
