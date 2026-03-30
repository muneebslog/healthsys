<?php

use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\QueueResetType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Doctor;
use App\Models\DoctorShareLedger;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guest is redirected from doctor share out', function () {
    $this->get(route('reception.doctor-share-out'))
        ->assertRedirect(route('login'));
});

test('staff can log doctor payout and marks invoice lines paid', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $family = Family::query()->create(['phone' => '03009998877']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Ledger Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Share Test',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'Consult',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);

    $sp = ServicePrice::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'price' => 1000,
        'doctor_share' => 70,
        'hospital_share' => 30,
        'is_active' => true,
    ]);

    $shift = Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $visit = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    $invoice = Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 1000,
        'discount' => 0,
        'final_amount' => 1000,
        'status' => InvoiceStatus::Paid,
    ]);

    $line = InvoiceService::query()->create([
        'invoice_id' => $invoice->id,
        'service_id' => $service->id,
        'service_price_id' => $sp->id,
        'doctor_id' => $doctor->id,
        'price' => 1000,
        'doctor_share_amount' => 700,
        'discount' => 0,
        'final_amount' => 1000,
        'doctor_share_paid' => false,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.doctor-share-out')
        ->set('doctorId', (string) $doctor->id)
        ->set('period', 'today')
        ->call('confirmPay')
        ->assertSet('showPayModal', true)
        ->call('logAndPay')
        ->assertSet('showPayModal', false)
        ->assertHasNoErrors();

    expect($line->fresh()->doctor_share_paid)->toBeTrue();

    $ledger = DoctorShareLedger::query()->where('doctor_id', $doctor->id)->first();
    expect($ledger)->not->toBeNull()
        ->and((int) $ledger->total_share)->toBe(700)
        ->and($ledger->paid_by)->toBe($staff->id);

    expect($ledger->items()->count())->toBe(1);
});
