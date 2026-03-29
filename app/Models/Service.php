<?php

namespace App\Models;

use App\Enums\QueueResetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = ['name', 'is_standalone', 'reset_type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_standalone' => 'boolean',
            'reset_type' => QueueResetType::class,
            'is_active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'service_prices', 'service_id', 'doctor_id');
    }

    public function priceForDoctor(?int $doctorId): ?ServicePrice
    {
        return $this->prices()->where('doctor_id', $doctorId)->first();
    }
}
