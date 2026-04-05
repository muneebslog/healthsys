<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabApiRequestLog extends Model
{
    protected $fillable = [
        'invoice_id',
        'method',
        'url',
        'request_headers',
        'request_body',
        'response_status',
        'response_body',
        'succeeded',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body' => 'array',
            'succeeded' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
