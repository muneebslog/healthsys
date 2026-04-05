<?php

use App\Enums\UserRole;
use App\Models\Doctor;
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
