<?php

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
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
