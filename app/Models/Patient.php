<?php

namespace App\Models;

use App\Enums\PatientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = ['name', 'gender', 'type', 'relation_to_head', 'family_id'];

    protected function casts(): array
    {
        return [
            'type' => PatientType::class,
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function queueTokens(): HasMany
    {
        return $this->hasMany(QueueToken::class);
    }
}
