<?php

namespace App\Models;

use App\Enums\LabTestSourcing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLabTest extends Model
{
    protected $fillable = [
        'invoice_id',
        'lab_test_id',
        'test_code',
        'test_name',
        'sourcing',
        'days_required',
        'hospital_share',
        'lab_share',
        'list_price',
        'line_discount',
        'line_final_amount',
        'hospital_share_amount',
        'lab_share_amount',
    ];

    protected function casts(): array
    {
        return [
            'sourcing' => LabTestSourcing::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function labTest(): BelongsTo
    {
        return $this->belongsTo(LabTest::class);
    }
}
