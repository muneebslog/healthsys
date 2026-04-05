<?php

use App\Enums\QueueResetType;
use App\Enums\QueueStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access admin services', function () {
    $this->get(route('admin.services'))
        ->assertRedirect(route('login'));
});

test('staff cannot access admin services', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($user)
        ->get(route('admin.services'))
        ->assertForbidden();
});

test('staff cannot access admin users', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('staff cannot access admin lab API log', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($user)
        ->get(route('admin.lab-api-logs'))
        ->assertForbidden();
});

test('admin can access settings crud pages', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user);

    $this->get(route('admin.services'))->assertOk();
    $this->get(route('admin.doctors'))->assertOk();
    $this->get(route('admin.service-prices'))->assertOk();
    $this->get(route('admin.users'))->assertOk();
    $this->get(route('admin.lab-api-logs'))->assertOk();
    $this->get(route('admin.queue-insights'))->assertOk();
    $this->get(route('admin.appointment-contacts'))->assertOk();

    $shiftOpener = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $shiftOpener->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'AdminSettingsQueueShow',
        'is_standalone' => true,
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
    $this->get(route('admin.queue-insights.show', $queue))->assertOk();
});

test('authenticated layout includes csrf meta for livewire requests', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->get(route('admin.doctors'))
        ->assertOk()
        ->assertSee('name="csrf-token"', false);
});

test('admin can run doctor edit action on doctors livewire page', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $doctor = Doctor::query()->create([
        'name' => 'Dr Livewire CSRF',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::admin.doctors')
        ->call('openEdit', $doctor->id)
        ->assertSet('showModal', true)
        ->assertSet('editingId', $doctor->id)
        ->assertSet('name', 'Dr Livewire CSRF');
});
