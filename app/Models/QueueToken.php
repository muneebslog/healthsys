<?php

namespace App\Models;

use App\Enums\QueueTokenStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueToken extends Model
{
    protected $fillable = [
        'queue_id', 'visit_id', 'patient_id', 'appointment_id',
        'token_number', 'status', 'reserved_at', 'called_at', 'completed_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QueueTokenStatus::class,
            'reserved_at' => 'datetime',
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
