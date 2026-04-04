<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\ProcedureStatus;
use Database\Factories\ProcedureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    /** @use HasFactory<ProcedureFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_number', 'patient_id', 'doctor_id', 'operation_name', 'package_price',
        'room_number', 'procedure_date', 'notes', 'status', 'admission_at', 'discharge_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProcedureStatus::class,
            'procedure_date' => 'date',
            'admission_at' => 'datetime',
            'discharge_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function totalPaidAmount(): int
    {
        return (int) $this->invoices()
            ->where('status', InvoiceStatus::Paid)
            ->sum('final_amount');
    }

    public function balanceAmount(): int
    {
        return (int) $this->package_price - $this->totalPaidAmount();
    }
}
