<?php

namespace App\Services;

use App\Models\LabTest;

/**
 * Builds per-line discount splits and hospital/lab share amounts for lab invoices.
 */
class LabInvoiceLineAllocator
{
    /**
     * @param  list<LabTest>  $tests  Order preserved; must be non-empty.
     * @return list<array{
     *     lab_test_id: int,
     *     test_code: string,
     *     test_name: string,
     *     sourcing: string,
     *     days_required: int,
     *     hospital_share: int,
     *     lab_share: int,
     *     list_price: int,
     *     line_discount: int,
     *     line_final_amount: int,
     *     hospital_share_amount: int,
     *     lab_share_amount: int,
     * }>
     */
    public static function allocateLines(array $tests, int $discountPercent): array
    {
        if ($tests === []) {
            return [];
        }

        $discountPercent = max(0, min(100, $discountPercent));

        $prices = [];
        foreach ($tests as $test) {
            $prices[] = (int) $test->price;
        }

        $subtotal = (int) array_sum($prices);
        $discountTotal = (int) floor($subtotal * $discountPercent / 100);
        $n = count($prices);

        $lineDiscounts = array_fill(0, $n, 0);
        $remaining = $discountTotal;

        for ($i = 0; $i < $n; $i++) {
            if ($i === $n - 1) {
                $lineDiscounts[$i] = $remaining;
            } else {
                $d = $subtotal > 0 ? (int) floor($discountTotal * $prices[$i] / $subtotal) : 0;
                $lineDiscounts[$i] = $d;
                $remaining -= $d;
            }
        }

        $rows = [];

        foreach ($tests as $i => $test) {
            $listPrice = $prices[$i];
            $lineDiscount = $lineDiscounts[$i];
            $lineFinal = $listPrice - $lineDiscount;

            $hospitalPct = (int) $test->hospital_share;
            $hospitalAmount = (int) round($lineFinal * $hospitalPct / 100);
            $labAmount = $lineFinal - $hospitalAmount;

            $rows[] = [
                'lab_test_id' => $test->id,
                'test_code' => $test->test_code,
                'test_name' => $test->name,
                'sourcing' => $test->sourcing instanceof \BackedEnum ? $test->sourcing->value : (string) $test->sourcing,
                'days_required' => (int) $test->days_required,
                'hospital_share' => $hospitalPct,
                'lab_share' => (int) $test->lab_share,
                'list_price' => $listPrice,
                'line_discount' => $lineDiscount,
                'line_final_amount' => $lineFinal,
                'hospital_share_amount' => $hospitalAmount,
                'lab_share_amount' => $labAmount,
            ];
        }

        return $rows;
    }
}
