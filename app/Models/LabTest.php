<?php

namespace App\Models;

use App\Enums\LabTestSourcing;
use Database\Factories\LabTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTest extends Model
{
    /** @use HasFactory<LabTestFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'test_code',
        'sourcing',
        'days_required',
        'price',
        'hospital_share',
        'lab_share',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sourcing' => LabTestSourcing::class,
            'is_active' => 'boolean',
        ];
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLabTest::class);
    }
}
