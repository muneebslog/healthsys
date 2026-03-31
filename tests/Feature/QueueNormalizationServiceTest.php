<?php

use App\Enums\QueueStatus;
use App\Models\Doctor;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use App\Services\QueueNormalizationService;

it('merges non-conflicting duplicate active queues and closes them', function () {
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
        'name' => 'Dr Normalize',
        'status' => 'active',
    ]);

    $primary = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 5,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    $dupe = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 0,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    QueueToken::query()->create([
        'queue_id' => $primary->id,
        'token_number' => 1,
        'status' => 'waiting',
    ]);
    QueueToken::query()->create([
        'queue_id' => $dupe->id,
        'token_number' => 7,
        'status' => 'waiting',
    ]);

    $report = app(QueueNormalizationService::class)->normalizeActiveQueues();

    expect($report['merged_tokens'])->toBe(1);
    expect($report['conflicts'])->toBe([]);

    expect(QueueToken::query()->where('queue_id', $primary->id)->pluck('token_number')->all())
        ->toContain(1)
        ->toContain(7);

    $dupe->refresh();
    expect($dupe->status)->toBe(QueueStatus::Closed);

    $primary->refresh();
    expect((int) $primary->current_token)->toBeGreaterThanOrEqual(7);
});

it('reports conflicts and leaves conflicting duplicates active', function () {
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
        'name' => 'Dr Conflict',
        'status' => 'active',
    ]);

    $primary = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 1,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    $dupe = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 0,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    QueueToken::query()->create([
        'queue_id' => $primary->id,
        'token_number' => 3,
        'status' => 'waiting',
    ]);
    QueueToken::query()->create([
        'queue_id' => $dupe->id,
        'token_number' => 3,
        'status' => 'waiting',
    ]);

    $report = app(QueueNormalizationService::class)->normalizeActiveQueues();

    expect($report['conflicts'])->not->toBeEmpty();

    $dupe->refresh();
    expect($dupe->status)->toBe(QueueStatus::Active);
});
