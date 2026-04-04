<?php

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\ProcedureStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Doctor;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sums paid invoices by kind for a shift', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $family = Family::query()->create(['phone' => '03001234567']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Kind Totals Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $shift = Shift::query()->create([
        'opened_by' => $user->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $visitOpd = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    $visitLab = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Proc',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    $procedure = Procedure::query()->create([
        'reference_number' => 'OT-1',
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'operation_name' => 'D&C',
        'package_price' => 10_000,
        'room_number' => null,
        'procedure_date' => null,
        'notes' => null,
        'status' => ProcedureStatus::Scheduled,
        'admission_at' => null,
        'discharge_at' => null,
    ]);

    $visitProc = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    Invoice::query()->create([
        'visit_id' => $visitOpd->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'kind' => InvoiceKind::Opd,
        'total_amount' => 300,
        'discount' => 0,
        'final_amount' => 300,
        'status' => InvoiceStatus::Paid,
    ]);

    Invoice::query()->create([
        'visit_id' => $visitLab->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'kind' => InvoiceKind::Lab,
        'total_amount' => 500,
        'discount' => 0,
        'final_amount' => 500,
        'status' => InvoiceStatus::Paid,
    ]);

    Invoice::query()->create([
        'visit_id' => $visitLab->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'kind' => InvoiceKind::Lab,
        'total_amount' => 200,
        'discount' => 0,
        'final_amount' => 200,
        'status' => InvoiceStatus::Draft,
    ]);

    Invoice::query()->create([
        'visit_id' => $visitProc->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'procedure_id' => $procedure->id,
        'kind' => InvoiceKind::Procedure,
        'total_amount' => 150,
        'discount' => 0,
        'final_amount' => 150,
        'status' => InvoiceStatus::Paid,
    ]);

    expect($shift->totalPaidInvoicesForKind(InvoiceKind::Opd))->toBe(300)
        ->and($shift->totalPaidInvoicesForKind(InvoiceKind::Lab))->toBe(500)
        ->and($shift->totalPaidInvoicesForKind(InvoiceKind::Procedure))->toBe(150)
        ->and($shift->totalInvoices())->toBe(950);
});
