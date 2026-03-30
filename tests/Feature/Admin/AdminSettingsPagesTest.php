<?php

use App\Enums\UserRole;
use App\Models\User;

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

test('admin can access settings crud pages', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user);

    $this->get(route('admin.services'))->assertOk();
    $this->get(route('admin.doctors'))->assertOk();
    $this->get(route('admin.service-prices'))->assertOk();
    $this->get(route('admin.users'))->assertOk();
});
