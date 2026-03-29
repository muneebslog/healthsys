<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitService extends Model
{
    protected $fillable = [
        'visit_id', 'service_id', 'doctor_id', 'service_price_id', 'queue_token_id', 'status',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function servicePrice(): BelongsTo
    {
        return $this->belongsTo(ServicePrice::class);
    }

    public function queueToken(): BelongsTo
    {
        return $this->belongsTo(QueueToken::class);
    }
}
