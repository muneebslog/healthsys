<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id', 'family_id', 'doctor_id', 'service_id',
        'queue_token_id', 'created_by', 'appointment_date',
        'appointment_time', 'status', 'notes', 'sms_sent',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'appointment_date' => 'date',
            'sms_sent' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function queueToken(): BelongsTo
    {
        return $this->belongsTo(QueueToken::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
