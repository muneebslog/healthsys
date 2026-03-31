<?php

use App\Enums\QueueResetType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('staff can create a quick walk-in without phone for eligible service', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $service = Service::query()->create([
        'name' => 'BP',
        'is_standalone' => true,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
        'allow_walkin_without_phone' => true,
    ]);

    ServicePrice::query()->create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'price' => 50,
        'doctor_share' => 0,
        'hospital_share' => 100,
        'is_active' => true,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.walk-in')
        ->call('switchToQuickMode')
        ->set('quickName', 'Walkin Elderly')
        ->call('createQuickWalkIn')
        ->assertHasNoErrors()
        ->set('pendingServiceId', (string) $service->id)
        ->call('addLine')
        ->assertHasNoErrors()
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $family = Family::query()->latest('id')->first();
    expect($family)->not->toBeNull()
        ->and($family->phone)->toBeNull();

    $invoice = Invoice::query()->with('patient')->latest('id')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->patient?->name)->toBe('Walkin Elderly');
});
