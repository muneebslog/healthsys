<?php

use App\Enums\QueueStatus;
use App\Models\Doctor;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;

it('returns existing active queue and closes empty duplicates', function () {
    $user = User::factory()->create();

    $shift = Shift::query()->create([
        'opened_by' => $user->id,
        'opening_balance' => 0,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    $service = Service::query()->create([
        'name' => 'Consultation',
        'is_standalone' => false,
        'reset_type' => 'daily',
        'is_active' => true,
    ]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Test',
        'status' => 'active',
    ]);

    $primary = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 10,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    $emptyDupe = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 0,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    $withTokensDupe = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 0,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    QueueToken::query()->create([
        'queue_id' => $withTokensDupe->id,
        'token_number' => 1,
        'status' => 'waiting',
    ]);

    $found = Queue::findOrCreateActiveForShift($service->id, $doctor->id, $shift->id);

    expect($found->id)->toBe($primary->id);

    expect(Queue::query()->findOrFail($emptyDupe->id)->status)->toBe(QueueStatus::Closed);
    expect(Queue::query()->findOrFail($emptyDupe->id)->closed_at)->not->toBeNull();

    expect(Queue::query()->findOrFail($withTokensDupe->id)->status)->toBe(QueueStatus::Active);
    expect(QueueToken::query()->where('queue_id', $withTokensDupe->id)->count())->toBe(1);
});
