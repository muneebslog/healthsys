<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceService extends Model
{
    protected $fillable = [
        'invoice_id', 'service_id', 'service_price_id', 'doctor_id', 'price',
        'doctor_share_amount', 'discount', 'final_amount', 'doctor_share_paid',
    ];

    protected function casts(): array
    {
        return [
            'doctor_share_paid' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function servicePrice(): BelongsTo
    {
        return $this->belongsTo(ServicePrice::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function ledgerItems(): HasMany
    {
        return $this->hasMany(DoctorShareLedgerItem::class);
    }

    /**
     * Invoice lines where the doctor’s share is still owed (not yet logged as paid out).
     */
    public function scopeUnpaidDoctorShare(Builder $query): Builder
    {
        $t = $query->getModel()->getTable();

        return $query
            ->where($t.'.doctor_share_paid', false)
            ->where($t.'.doctor_share_amount', '>', 0)
            ->whereNotNull($t.'.doctor_id');
    }
}
