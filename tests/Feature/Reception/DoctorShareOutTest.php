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

test('payout reconciles first-five full share before recording ledger total', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $family = Family::query()->create(['phone' => '03008887766']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Reconcile Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr First Five',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'first_five_slips_full_share' => true,
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
        'price' => 500,
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
        'total_amount' => 500,
        'discount' => 0,
        'final_amount' => 500,
        'status' => InvoiceStatus::Paid,
    ]);

    $line = InvoiceService::query()->create([
        'invoice_id' => $invoice->id,
        'service_id' => $service->id,
        'service_price_id' => $sp->id,
        'doctor_id' => $doctor->id,
        'price' => 500,
        'doctor_share_amount' => 350,
        'discount' => 0,
        'final_amount' => 500,
        'doctor_share_paid' => false,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.doctor-share-out')
        ->set('doctorId', (string) $doctor->id)
        ->set('period', 'today')
        ->call('confirmPay')
        ->call('logAndPay')
        ->assertHasNoErrors();

    expect($line->fresh()->doctor_share_amount)->toBe(500)
        ->and($line->fresh()->doctor_share_paid)->toBeTrue();

    $ledger = DoctorShareLedger::query()->where('doctor_id', $doctor->id)->first();
    expect($ledger)->not->toBeNull()
        ->and((int) $ledger->total_share)->toBe(500);
});

test('doctor share out page lists all doctors with pending share for today', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $family = Family::query()->create(['phone' => '03001234567']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Pending Table Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $doctorA = Doctor::query()->create([
        'name' => 'Dr Pending Alpha',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    $doctorB = Doctor::query()->create([
        'name' => 'Dr Pending Beta',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'Consult Pending',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);

    $spA = ServicePrice::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctorA->id,
        'price' => 1000,
        'doctor_share' => 50,
        'hospital_share' => 50,
        'is_active' => true,
    ]);

    $spB = ServicePrice::query()->create([
        'service_id' => $service->id,
        'doctor_id' => $doctorB->id,
        'price' => 2000,
        'doctor_share' => 50,
        'hospital_share' => 50,
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
        'doctor_id' => $doctorA->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    $invoice = Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 3000,
        'discount' => 0,
        'final_amount' => 3000,
        'status' => InvoiceStatus::Paid,
    ]);

    InvoiceService::query()->create([
        'invoice_id' => $invoice->id,
        'service_id' => $service->id,
        'service_price_id' => $spA->id,
        'doctor_id' => $doctorA->id,
        'price' => 1000,
        'doctor_share_amount' => 500,
        'discount' => 0,
        'final_amount' => 1000,
        'doctor_share_paid' => false,
    ]);

    InvoiceService::query()->create([
        'invoice_id' => $invoice->id,
        'service_id' => $service->id,
        'service_price_id' => $spB->id,
        'doctor_id' => $doctorB->id,
        'price' => 2000,
        'doctor_share_amount' => 1000,
        'discount' => 0,
        'final_amount' => 2000,
        'doctor_share_paid' => false,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.doctor-share-out')
        ->assertSee('Dr Pending Alpha')
        ->assertSee('Dr Pending Beta')
        ->assertSee(number_format(500))
        ->assertSee(number_format(1000));
});

test('shift net does not subtract unpaid doctor share', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Net Test',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'Consult Net',
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

    $family = Family::query()->create(['phone' => '03001112233']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Net Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

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

    expect($shift->fresh()->totalInvoices())->toBe(1000)
        ->and($shift->fresh()->totalDoctorPayouts())->toBe(0)
        ->and($shift->fresh()->totalDoctorSharesAccrued())->toBe(700)
        ->and($shift->fresh()->netAmount())->toBe(1000);

    Livewire::actingAs($staff)
        ->test('pages::reception.doctor-share-out')
        ->set('doctorId', (string) $doctor->id)
        ->set('period', 'today')
        ->call('confirmPay')
        ->call('logAndPay')
        ->assertHasNoErrors();

    expect($line->fresh()->doctor_share_paid)->toBeTrue();

    expect($shift->fresh()->totalDoctorPayouts())->toBe(700)
        ->and($shift->fresh()->netAmount())->toBe(300);
});

test('finance manager payout does not affect shift net', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $finance = User::factory()->create(['role' => UserRole::FinanceManager]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Finance Net Test',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'Consult Finance Net',
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

    $family = Family::query()->create(['phone' => '03005556677']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Finance Net Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

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

    $ledger = DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $finance->id,
        'period_from' => now()->subDays(14)->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 700,
        'paid_at' => now(),
        'notes' => null,
    ]);

    $ledger->items()->create(['invoice_service_id' => $line->id]);
    $line->update(['doctor_share_paid' => true]);

    expect($shift->fresh()->totalDoctorSharesAccrued())->toBe(700)
        ->and($shift->fresh()->totalDoctorPayouts())->toBe(0)
        ->and($shift->fresh()->netAmount())->toBe(1000);
});
