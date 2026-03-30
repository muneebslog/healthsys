<?php

use App\Enums\QueueResetType;
use App\Enums\QueueStatus;
use App\Enums\QueueTokenStatus;
use App\Enums\ShiftStatus;
use App\Livewire\QueueControl;
use App\Models\Doctor;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Livewire\Livewire;

it('shows the queues index for authenticated staff', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('queues.index'))
        ->assertSuccessful();
});

it('renders queue control livewire for an active queue', function () {
    $user = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $user->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'Consultation',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $doctor = Doctor::create([
        'name' => 'Dr. Livewire',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);
    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 1,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(QueueControl::class, ['queue' => $queue])
        ->assertSuccessful()
        ->assertSee('Dr. Livewire')
        ->assertSee('Consultation')
        ->assertSee(__('Call next'));
});

it('call next promotes a waiting token via queue control', function () {
    $user = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $user->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'Consultation',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $doctor = Doctor::create([
        'name' => 'Dr. Q',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);
    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 1,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 1,
        'status' => QueueTokenStatus::Waiting,
    ]);

    Livewire::actingAs($user)
        ->test(QueueControl::class, ['queue' => $queue])
        ->call('callNext')
        ->assertHasNoErrors();

    expect(QueueToken::query()->where('queue_id', $queue->id)->where('status', QueueTokenStatus::Serving)->value('token_number'))->toBe(1);
});

it('returns 404 when controlling an inactive queue over http', function () {
    $user = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $user->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'Consultation',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Closed,
        'current_token' => 0,
        'current_flow_token' => 0,
        'closed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('queues.control', $queue))
        ->assertNotFound();
});
