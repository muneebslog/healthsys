<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Assigns monotonic sample-slip serial numbers for lab invoices.
 *
 * Must be called inside an open database transaction so increments roll back with the invoice.
 */
final class LabSampleSlipSerialAllocator
{
    public function allocateNext(): int
    {
        $row = DB::table('lab_sample_slip_counters')->where('id', 1)->lockForUpdate()->first();

        if ($row === null) {
            throw new \RuntimeException('Lab sample slip counter row is missing; run migrations.');
        }

        $serial = (int) $row->last_serial + 1;

        DB::table('lab_sample_slip_counters')->where('id', 1)->update(['last_serial' => $serial]);

        return $serial;
    }
}
