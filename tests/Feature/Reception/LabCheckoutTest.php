<?php

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\User;
use Livewire\Livewire;

test('lab checkout page defaults age unit to years', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->assertSet('patientAgeUnit', 'year');
});

test('staff can checkout lab tests with 0, 50, and 100 percent discount', function (int $discountPercent, int $expectedDiscount, int $expectedFinal) {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03001112233']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Lab Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt1 = LabTest::factory()->create([
        'test_code' => 'CBC-LAB',
        'price' => 1000,
        'hospital_share' => 60,
        'lab_share' => 40,
    ]);
    $lt2 = LabTest::factory()->create([
        'test_code' => 'LIP-LAB',
        'price' => 500,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03001112233')
        ->call('lookupPhone')
        ->assertSet('familyId', $family->id)
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 35)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt1->id, $lt2->id])
        ->set('discountPercent', $discountPercent)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->with('labTests')->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->kind)->toBe(InvoiceKind::Lab)
        ->and((int) $invoice->lab_sample_slip_serial)->toBe(1)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and((int) $invoice->total_amount)->toBe(1500)
        ->and((int) $invoice->discount)->toBe($expectedDiscount)
        ->and((int) $invoice->discount_percent)->toBe($discountPercent)
        ->and((int) $invoice->final_amount)->toBe($expectedFinal)
        ->and($invoice->labTests)->toHaveCount(2);

    $sumLines = (int) $invoice->labTests->sum('line_final_amount');
    expect($sumLines)->toBe($expectedFinal);

    $sumList = (int) $invoice->labTests->sum('list_price');
    expect($sumList)->toBe(1500);

    $sumLineDiscount = (int) $invoice->labTests->sum('line_discount');
    expect($sumLineDiscount)->toBe($expectedDiscount);

    $patient->refresh();
    expect($patient->age)->toBe(35)
        ->and($patient->age_unit)->toBe('year');
})->with([
    [0, 0, 1500],
    [50, 750, 750],
    [100, 1500, 0],
]);

test('lab checkout treats null discount percent like zero', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03009998877']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Null Discount Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => 'NULL-D',
        'price' => 800,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03009998877')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 22)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', null)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->with('labTests')->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and((int) $invoice->discount)->toBe(0)
        ->and((int) $invoice->discount_percent)->toBe(0)
        ->and((int) $invoice->final_amount)->toBe(800);
});

test('staff can print a lab invoice and see test codes', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03002223344']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Print Patient',
        'gender' => 'female',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => 'PRINT-99',
        'name' => 'Vitamin D',
        'price' => 200,
        'hospital_share' => 70,
        'lab_share' => 30,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03002223344')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 40)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->latest('id')->first();
    expect($invoice)->not->toBeNull()
        ->and((int) $invoice->lab_sample_slip_serial)->toBe(1);

    $response = $this->actingAs($staff)->get(route('invoices.print', $invoice));

    $response->assertOk();
    $response->assertSee('PRINT-99', false);
    $response->assertSee('Vitamin D', false);
    $response->assertSee('Sample serial', false);
    $response->assertSee(number_format((int) $invoice->lab_sample_slip_serial), false);
    $response->assertSee('Lab sample slip', false);
});

test('lab sample slip serial increments for each new lab invoice', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03005556677']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Serial Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $ltA = LabTest::factory()->create([
        'test_code' => 'SER-A',
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);
    $ltB = LabTest::factory()->create([
        'test_code' => 'SER-B',
        'price' => 200,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    $component = Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03005556677')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$ltA->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $first = Invoice::query()->latest('id')->first();
    expect($first)->not->toBeNull()
        ->and((int) $first->lab_sample_slip_serial)->toBe(1);

    $component
        ->set('selectedTestIds', [$ltB->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $second = Invoice::query()->latest('id')->first();
    expect($second)->not->toBeNull()
        ->and((int) $second->lab_sample_slip_serial)->toBe(2)
        ->and($second->id)->not->toBe($first->id);
});

test('lab checkout requires patient age before creating an invoice', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03008887766']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'No Age Yet',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => 'AGE-REQ',
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03008887766')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasErrors(['patientAge']);

    expect(Invoice::query()->count())->toBe(0);
});
