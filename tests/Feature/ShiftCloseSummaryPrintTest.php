<?php

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\ShiftExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest is redirected from shift close summary print', function () {
    $this->get(route('reception.shift-close-summary', ['shift' => 99]))
        ->assertRedirect(route('login'));
});

test('staff can view shift close summary print page', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $shift = Shift::query()->create([
        'opened_by' => $user->id,
        'opening_balance' => 1000,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    ShiftExpense::query()->create([
        'shift_id' => $shift->id,
        'created_by' => $user->id,
        'label' => 'Paper roll',
        'amount' => 50,
    ]);

    $this->actingAs($user)
        ->get(route('reception.shift-close-summary', $shift))
        ->assertOk()
        ->assertSee(config('hms.clinic_name', 'HMS'), false)
        ->assertSee((string) __('Shift close summary'), false)
        ->assertSee('1,000', false)
        ->assertSee('Paper roll', false)
        ->assertSee('50', false);
});

test('non-staff cannot view shift close summary print page', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $doctor = User::factory()->create(['role' => UserRole::Doctor]);

    $shift = Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $this->actingAs($doctor)
        ->get(route('reception.shift-close-summary', $shift))
        ->assertForbidden();
});
