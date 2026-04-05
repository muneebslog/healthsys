<?php

use App\Enums\QueueResetType;
use App\Enums\QueueStatus;
use App\Enums\QueueTokenStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access admin queue insights', function () {
    $this->get(route('admin.queue-insights'))
        ->assertRedirect(route('login'));

    $this->get(route('admin.queue-insights.show', ['queue' => 1]))
        ->assertRedirect(route('login'));
});

test('staff cannot access admin queue insights', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($user)
        ->get(route('admin.queue-insights'))
        ->assertForbidden();
});

test('staff cannot access admin queue insight show', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);
    $shiftOpener = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $shiftOpener->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'StaffBlockedShow',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 0,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('admin.queue-insights.show', $queue))
        ->assertForbidden();
});

test('admin can access queue insights and see queues in range', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $shiftOpener = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $shiftOpener->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'QueueInsightService',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $doctor = Doctor::create([
        'name' => 'Dr. Queue Insight',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);
    Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Closed,
        'current_token' => 1,
        'current_flow_token' => 0,
        'closed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.queue-insights'))
        ->assertOk()
        ->assertSee('QueueInsightService')
        ->assertSee('Dr. Queue Insight');
});

test('queue insights date filter excludes queues outside range', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $shiftOpener = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $shiftOpener->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'OutOfRangeQueueSvc',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $doctor = Doctor::create([
        'name' => 'Dr. Out Of Range',
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
        'current_token' => 0,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);
    $queue->forceFill(['created_at' => now()->subDays(30), 'updated_at' => now()->subDays(30)])->save();

    Livewire::actingAs($admin)
        ->test('pages::admin.queue-insights')
        ->set('dateFrom', now()->subDay()->toDateString())
        ->set('dateTo', now()->toDateString())
        ->assertDontSee('OutOfRangeQueueSvc');
});

test('admin can open queue token insight page for a queue', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $shiftOpener = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $shiftOpener->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'ShowPageQueueService',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $doctor = Doctor::create([
        'name' => 'Dr. Show Page',
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
    $patient = Patient::factory()->create(['name' => 'TokenShowPagePatient']);
    QueueToken::create([
        'queue_id' => $queue->id,
        'patient_id' => $patient->id,
        'token_number' => 7,
        'status' => QueueTokenStatus::Done,
        'reserved_at' => now()->subHour(),
        'called_at' => now()->subMinutes(30),
        'completed_at' => now()->subMinutes(5),
        'paid_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.queue-insights.show', $queue))
        ->assertOk()
        ->assertSee('ShowPageQueueService')
        ->assertSee('Dr. Show Page')
        ->assertSee('TokenShowPagePatient');
});
