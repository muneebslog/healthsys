<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = ['name', 'specialization', 'phone', 'status', 'is_on_payroll', 'user_id'];

    protected function casts(): array
    {
        return [
            'is_on_payroll' => 'boolean',
        ];
    }

    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_prices', 'doctor_id', 'service_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }

    public function shareLedger(): HasMany
    {
        return $this->hasMany(DoctorShareLedger::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
