<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class DoctorSharePayoutReceiptData
{
    public function __construct(
        public string $doctorName,
        public CarbonImmutable $paidAt,
        public string $periodFrom,
        public string $periodTo,
        public int $fullShareSlipCount,
        public int $baseShareSlipCount,
        public int $fullShareDistinctInvoiceCount,
        public int $baseShareDistinctInvoiceCount,
        public int $fullShareSubtotal,
        public int $baseShareSubtotal,
        public int $totalShare,
        public ?int $avgFullSharePerSlip,
        public ?int $avgBaseSharePerSlip,
        public ?int $baseSharePercentOfLine,
        public int $ledgerId,
        public ?string $paidByName,
        public ?string $notes,
    ) {}
}
