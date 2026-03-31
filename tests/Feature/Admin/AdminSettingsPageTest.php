<?php

use App\Enums\UserRole;
use App\Models\User;

it('allows admin to view admin settings page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.settings'))
        ->assertSuccessful()
        ->assertSee(__('Settings'));
});

it('forbids non-admin from viewing admin settings page', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($staff)
        ->get(route('admin.settings'))
        ->assertForbidden();
});
