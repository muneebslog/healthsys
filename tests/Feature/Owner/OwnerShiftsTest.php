<?php

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\DoctorShareLedger;
use App\Models\Shift;
use App\Models\User;

test('guests cannot access owner shifts', function () {
    $this->get(route('owner.shifts'))
        ->assertRedirect(route('login'));
});

test('staff cannot access owner shifts', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($user)
        ->get(route('owner.shifts'))
        ->assertForbidden();
});

test('owner can access shifts index and shift show', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($user);

    $this->get(route('owner.shifts'))->assertOk();

    $shift = Shift::query()->create([
        'opened_by' => $user->id,
        'opening_balance' => 1000,
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subDay(),
        'closed_by' => $user->id,
        'closed_at' => now()->subDay()->addHours(8),
    ]);

    $this->get(route('owner.shifts.show', $shift))->assertOk();
});

test('owner shifts page shows today doctor payout section with formatted total', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $doctor = Doctor::query()->create([
        'name' => 'Dr Payout Banner',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 12_345,
        'paid_at' => now(),
        'notes' => null,
    ]);

    $this->actingAs($owner)
        ->get(route('owner.shifts'))
        ->assertOk()
        ->assertSee('12,345', false)
        ->assertSee('Dr Payout Banner', false);
});
