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
use App\Services\DoctorShareCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('amount uses assigned percentage when first-five rule is off', function () {
    $doctor = Doctor::query()->create([
        'name' => 'Dr A',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'first_five_slips_full_share' => false,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'S',
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

    $sp->setRelation('doctor', $doctor);

    expect(DoctorShareCalculator::amountForLine($sp, 1000, 0))->toBe(700)
        ->and(DoctorShareCalculator::amountForLine($sp, 1000, 10))->toBe(700);
});

test('amount uses full line for first five slips when rule is on', function () {
    $doctor = Doctor::query()->create([
        'name' => 'Dr B',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'first_five_slips_full_share' => true,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'S2',
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

    $sp->setRelation('doctor', $doctor);

    expect(DoctorShareCalculator::amountForLine($sp, 1000, 0))->toBe(1000)
        ->and(DoctorShareCalculator::amountForLine($sp, 1000, 4))->toBe(1000)
        ->and(DoctorShareCalculator::amountForLine($sp, 1000, 5))->toBe(700);
});

test('amount is zero when service price has no doctor', function () {
    $service = Service::query()->create([
        'name' => 'S3',
        'is_standalone' => true,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);

    $sp = ServicePrice::query()->create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'price' => 500,
        'doctor_share' => 0,
        'hospital_share' => 100,
        'is_active' => true,
    ]);

    expect(DoctorShareCalculator::amountForLine($sp, 500, 0))->toBe(0);
});

test('countSlipsTodayForDoctor only counts same calendar day', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr C',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'first_five_slips_full_share' => false,
        'user_id' => null,
    ]);

    $family = Family::query()->create(['phone' => '03001112233']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'P',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $service = Service::query()->create([
        'name' => 'S4',
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

    $invoiceYesterday = Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 1000,
        'discount' => 0,
        'final_amount' => 1000,
        'status' => InvoiceStatus::Paid,
    ]);
    $invoiceYesterday->created_at = now()->subDay();
    $invoiceYesterday->updated_at = now()->subDay();
    $invoiceYesterday->saveQuietly();

    InvoiceService::query()->create([
        'invoice_id' => $invoiceYesterday->id,
        'service_id' => $service->id,
        'service_price_id' => $sp->id,
        'doctor_id' => $doctor->id,
        'price' => 1000,
        'doctor_share_amount' => 700,
        'discount' => 0,
        'final_amount' => 1000,
    ]);

    $invoiceToday = Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 1000,
        'discount' => 0,
        'final_amount' => 1000,
        'status' => InvoiceStatus::Paid,
    ]);

    InvoiceService::query()->create([
        'invoice_id' => $invoiceToday->id,
        'service_id' => $service->id,
        'service_price_id' => $sp->id,
        'doctor_id' => $doctor->id,
        'price' => 1000,
        'doctor_share_amount' => 700,
        'discount' => 0,
        'final_amount' => 1000,
    ]);

    expect(DoctorShareCalculator::countSlipsTodayForDoctor($doctor->id))->toBe(1);
});
