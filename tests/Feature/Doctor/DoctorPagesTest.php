<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;

test('guest is redirected from doctor dashboard', function () {
    $this->get(route('doctor.dashboard'))
        ->assertRedirect(route('login'));
});

test('staff cannot open doctor pages when role guards are enforced', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($staff)->get(route('doctor.dashboard'))
        ->assertForbidden();
});

test('doctor user without linked doctor profile cannot open doctor pages', function () {
    $user = User::factory()->create(['role' => UserRole::Doctor]);

    $this->actingAs($user)->get(route('doctor.dashboard'))
        ->assertForbidden();
});

test('doctor user with linked profile can open doctor pages', function () {
    $user = User::factory()->create(['role' => UserRole::Doctor]);
    Doctor::query()->create([
        'name' => 'Dr Portal',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->get(route('doctor.dashboard'))->assertOk();
    $this->actingAs($user)->get(route('doctor.profile'))->assertOk();
    $this->actingAs($user)->get(route('doctor.payouts'))->assertOk();
    $this->actingAs($user)->get(route('doctor.queue'))->assertOk();
    $this->actingAs($user)->get(route('doctor.processes'))->assertOk();
});
