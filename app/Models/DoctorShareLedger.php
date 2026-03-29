<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorShareLedger extends Model
{
    protected $table = 'doctor_share_ledger';

    protected $fillable = ['doctor_id', 'paid_by', 'period_from', 'period_to', 'total_share', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DoctorShareLedgerItem::class, 'ledger_id');
    }
}
