<?php

namespace App\Models;

use App\Enums\QueueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Queue extends Model
{
    protected $fillable = [
        'service_id', 'doctor_id', 'shift_id', 'status',
        'current_token', 'current_flow_token', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QueueStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(QueueToken::class);
    }

    public function isActive(): bool
    {
        return is_null($this->closed_at) && $this->status !== QueueStatus::Finished;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('closed_at')
            ->where('status', '!=', QueueStatus::Finished);
    }

    public function assignNextToken(): int
    {
        return (int) DB::transaction(function () {
            $queue = static::lockForUpdate()->findOrFail($this->id);

            // Walk-ins must not collide with already reserved tokens (from appointment slots).
            // We scan forward from the next counter value and pick the first unused token_number.
            $candidate = (int) $queue->current_token + 1;
            while (QueueToken::query()
                ->where('queue_id', $queue->id)
                ->where('token_number', $candidate)
                ->exists()) {
                $candidate++;
            }

            $queue->current_token = $candidate;
            $queue->save();

            return $candidate;
        });
    }
}
