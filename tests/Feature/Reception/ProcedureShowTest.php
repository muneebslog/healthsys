<?php

use App\Enums\ProcedureStatus;
use App\Enums\UserRole;
use App\Models\Procedure;
use App\Models\User;
use Livewire\Livewire;

it('allows staff to view the procedure show page', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $procedure = Procedure::factory()->create();

    $this->actingAs($staff)
        ->get(route('reception.procedures.show', $procedure))
        ->assertSuccessful()
        ->assertSee(__('Financial summary'))
        ->assertSee(__('Change package price'));
});

it('saves package price from the modal and closes it', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $procedure = Procedure::factory()->create(['package_price' => 20_000]);

    Livewire::actingAs($staff)
        ->test('pages::reception.procedure-show', ['procedure' => $procedure])
        ->assertSet('showPackagePriceModal', false)
        ->call('openPackagePriceModal')
        ->assertSet('showPackagePriceModal', true)
        ->set('packagePriceInput', '25000')
        ->call('savePackagePrice')
        ->assertHasNoErrors()
        ->assertSet('showPackagePriceModal', false);

    expect($procedure->fresh()->package_price)->toBe(25_000);
});

it('updates status admission and discharge from case progress', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $procedure = Procedure::factory()->create([
        'status' => ProcedureStatus::Scheduled,
        'admission_at' => null,
        'discharge_at' => null,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.procedure-show', ['procedure' => $procedure])
        ->set('caseStatus', ProcedureStatus::InProgress->value)
        ->set('admissionInput', '2026-04-10T08:30')
        ->set('dischargeInput', '2026-04-10T18:00')
        ->call('saveCaseProgress')
        ->assertHasNoErrors();

    $procedure->refresh();
    expect($procedure->status)->toBe(ProcedureStatus::InProgress)
        ->and($procedure->admission_at)->not->toBeNull()
        ->and($procedure->discharge_at)->not->toBeNull();
});

it('rejects discharge before admission', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $procedure = Procedure::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::reception.procedure-show', ['procedure' => $procedure])
        ->set('caseStatus', ProcedureStatus::InProgress->value)
        ->set('admissionInput', '2026-04-10T18:00')
        ->set('dischargeInput', '2026-04-10T08:00')
        ->call('saveCaseProgress')
        ->assertHasErrors('dischargeInput');
});
