<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = ['visit_id', 'patient_id', 'shift_id', 'total_amount', 'discount', 'final_amount', 'status'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(InvoiceService::class);
    }
}
