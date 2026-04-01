<?php

use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest is redirected from invoices index', function () {
    /** @noinspection PhpUndefinedMethodInspection */
    $this->get(route('invoices.index'))
        ->assertRedirect(route('login'));
});

test('staff can view invoices index', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $family = Family::query()->create(['phone' => '03001234567']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Test Patient',
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

    $visit = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 500,
        'discount' => 0,
        'final_amount' => 500,
        'status' => InvoiceStatus::Paid,
    ]);

    $otherFamily = Family::query()->create(['phone' => '03001234570']);
    $otherPatient = Patient::query()->create([
        'family_id' => $otherFamily->id,
        'name' => 'Other Shift Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $otherFamily->update(['head_id' => $otherPatient->id]);

    $otherShift = Shift::query()->create([
        'opened_by' => $user->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay()->addHours(8),
        'closed_by' => $user->id,
    ]);

    $otherVisit = Visit::query()->create([
        'patient_id' => $otherPatient->id,
        'family_id' => $otherFamily->id,
        'doctor_id' => null,
        'shift_id' => $otherShift->id,
        'status' => VisitStatus::InProgress,
    ]);

    Invoice::query()->create([
        'visit_id' => $otherVisit->id,
        'patient_id' => $otherPatient->id,
        'shift_id' => $otherShift->id,
        'total_amount' => 700,
        'discount' => 0,
        'final_amount' => 700,
        'status' => InvoiceStatus::Paid,
    ]);

    /** @noinspection PhpUndefinedMethodInspection */
    $this->actingAs($user)
        ->get(route('invoices.index'))
        ->assertSuccessful()
        ->assertSee('Test Patient')
        ->assertDontSee('Other Shift Patient');
});

test('admin can view invoices index', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $family = Family::query()->create(['phone' => '03001234568']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Test Patient',
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

    $visit = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 500,
        'discount' => 0,
        'final_amount' => 500,
        'status' => InvoiceStatus::Paid,
    ]);

    /** @noinspection PhpUndefinedMethodInspection */
    $this->actingAs($user)
        ->get(route('invoices.index'))
        ->assertSuccessful()
        ->assertSee('Test Patient');
});

test('owner can view invoices index', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);

    $family = Family::query()->create(['phone' => '03001234569']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Test Patient',
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

    $visit = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 500,
        'discount' => 0,
        'final_amount' => 500,
        'status' => InvoiceStatus::Paid,
    ]);

    /** @noinspection PhpUndefinedMethodInspection */
    $this->actingAs($user)
        ->get(route('invoices.index'))
        ->assertSuccessful()
        ->assertSee('Test Patient');
});

test('doctor cannot view invoices index', function () {
    $user = User::factory()->create(['role' => UserRole::Doctor]);

    /** @noinspection PhpUndefinedMethodInspection */
    $this->actingAs($user)
        ->get(route('invoices.index'))
        ->assertForbidden();
});
