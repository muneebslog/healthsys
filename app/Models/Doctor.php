<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'name', 'specialization', 'phone', 'start_time', 'end_time',
        'status', 'is_on_payroll', 'first_five_slips_full_share', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_on_payroll' => 'boolean',
            'first_five_slips_full_share' => 'boolean',
        ];
    }

    /**
     * Whether this doctor has valid working hours for the appointments grid.
     */
    public function hasWorkingHours(): bool
    {
        if ($this->start_time === null || $this->start_time === ''
            || $this->end_time === null || $this->end_time === '') {
            return false;
        }

        $day = '2000-01-01';
        $start = Carbon::parse($day.' '.$this->start_time);
        $end = Carbon::parse($day.' '.$this->end_time);

        return $end->gt($start);
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
