<?php

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Procedure;
use App\Models\Shift;
use App\Models\User;
use App\Services\ProcedurePaymentRecorder;
use Livewire\Livewire;

test('procedure payment recorder creates paid procedure invoices and totals', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $shift = Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $doctor = Doctor::factory()->create();
    $procedure = Procedure::factory()->create([
        'doctor_id' => $doctor->id,
        'package_price' => 50_000,
    ]);

    $recorder = app(ProcedurePaymentRecorder::class);
    $recorder->record($procedure, $shift, 10_000);
    $recorder->record($procedure->fresh(), $shift, 5_000);

    expect($procedure->fresh()->totalPaidAmount())->toBe(15_000)
        ->and($procedure->fresh()->balanceAmount())->toBe(35_000);

    $kinds = Invoice::query()->where('procedure_id', $procedure->id)->pluck('kind')->all();
    expect($kinds)->each->toBe(InvoiceKind::Procedure);

    expect(Invoice::query()->where('procedure_id', $procedure->id)->where('status', InvoiceStatus::Paid)->count())->toBe(2);
});

test('doctor processes page only shows procedures for the logged-in doctor', function () {
    $userA = User::factory()->create(['role' => UserRole::Doctor]);
    $docA = Doctor::factory()->create(['user_id' => $userA->id]);
    $docB = Doctor::factory()->create();

    Procedure::factory()->create([
        'doctor_id' => $docA->id,
        'reference_number' => 'DOC-A-ONLY',
        'procedure_date' => now()->toDateString(),
    ]);

    Procedure::factory()->create([
        'doctor_id' => $docB->id,
        'reference_number' => 'DOC-B-SECRET',
        'procedure_date' => now()->toDateString(),
    ]);

    Livewire::actingAs($userA)
        ->test('pages::doctor.processes')
        ->assertSee('DOC-A-ONLY')
        ->assertDontSee('DOC-B-SECRET');
});

test('staff can print procedure payment invoice', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $shift = Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $procedure = Procedure::factory()->create([
        'operation_name' => 'TestOpUnique',
        'reference_number' => 'REF-PRINT-99',
    ]);

    $invoice = app(ProcedurePaymentRecorder::class)->record($procedure, $shift, 2_500);

    $this->actingAs($staff)
        ->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('TestOpUnique', false)
        ->assertSee('REF-PRINT-99', false);
});
