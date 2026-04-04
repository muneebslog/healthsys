<?php

use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\QueueResetType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Doctor;
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

test('finance manager can access finance dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::FinanceManager]);

    $this->actingAs($user)
        ->get(route('finance.dashboard'))
        ->assertOk();
});

test('non finance user cannot access finance routes', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($staff)
        ->get(route('finance.dashboard'))
        ->assertForbidden();
});

test('finance manager can view owner shifts and shift detail', function () {
    $user = User::factory()->create(['role' => UserRole::FinanceManager]);
    $shift = Shift::query()->create([
        'opened_by' => $user->id,
        'closed_by' => null,
        'opening_balance' => 0,
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('owner.shifts'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('owner.shifts.show', $shift))
        ->assertOk();
});

test('finance manager can view invoices index', function () {
    $user = User::factory()->create(['role' => UserRole::FinanceManager]);

    $this->actingAs($user)
        ->get(route('invoices.index'))
        ->assertOk();
});

test('finance manager can download csv export', function () {
    $user = User::factory()->create(['role' => UserRole::FinanceManager]);

    $this->actingAs($user)
        ->get(route('finance.export.download', [
            'type' => 'invoices',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('finance manager cannot log doctor payout on share out page', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $finance = User::factory()->create(['role' => UserRole::FinanceManager]);

    $family = Family::query()->create(['phone' => '03001112233']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Audit Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Audit',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'Consult Audit',
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

    Livewire::actingAs($finance)
        ->test('pages::reception.doctor-share-out')
        ->set('doctorId', (string) $doctor->id)
        ->set('period', 'today')
        ->call('confirmPay')
        ->assertSet('showPayModal', false)
        ->call('logAndPay');

    expect($line->fresh()->doctor_share_paid)->toBeFalse();
});
