<?php

use App\Models\User;

it('shows the appointments page for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reception.appointments'))
        ->assertSuccessful()
        ->assertSee(__("Today's Appointments"));
});
