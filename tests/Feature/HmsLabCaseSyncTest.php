<?php

use App\Enums\InvoiceKind;
use App\Enums\LabTestSourcing;
use App\Enums\PatientType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\LabApiRequestLog;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('does not call the external lab API when the bearer token is not configured', function () {
    Http::fake();

    Config::set('hms.lab_cases.api_token', null);
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

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
        'name' => 'No Sync Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => 'NO-SYNC',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03001112233')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertNothingSent();
});

it('posts a lab case to the external HMS after checkout when the token is configured', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([
            'message' => 'Lab case created.',
            'patient_id' => 1,
            'invoice_url' => 'https://lab.mohsinmedicalcomplex.com/invoice/1',
        ], 201),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);
    Config::set('hms.lab_cases.invoice_number_prefix', 'HS-');

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
        'name' => 'Sync Patient',
        'gender' => 'female',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => '1300',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 1000,
        'hospital_share' => 60,
        'lab_share' => 40,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03001112233')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('kind', InvoiceKind::Lab)->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->lab_case_invoice_url)->toBe('https://lab.mohsinmedicalcomplex.com/invoice/1');

    Http::assertSent(function ($request) use ($invoice) {
        $auth = $request->header('Authorization')[0] ?? '';

        return $request->url() === 'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases'
            && $auth === 'Bearer test-bearer-token'
            && $request['name'] === 'Sync Patient'
            && $request['phone'] === '03001112233'
            && $request['gender'] === 'female'
            && $request['invoice_number'] === 'HS-'.$invoice->id
            && $request['test_codes'] === [1300]
            && $request['age'] === 30
            && $request['age_unit'] === 'Year';
    });

    $log = LabApiRequestLog::query()->where('invoice_id', $invoice->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->succeeded)->toBeTrue()
        ->and($log->response_status)->toBe(201)
        ->and($log->request_headers['Authorization'] ?? null)->toBe('Bearer [redacted]')
        ->and($log->request_body)->toMatchArray([
            'name' => 'Sync Patient',
            'phone' => '03001112233',
            'gender' => 'female',
            'invoice_number' => 'HS-'.$invoice->id,
            'test_codes' => [1300],
            'age' => 30,
            'age_unit' => 'Year',
        ]);

    $patient->refresh();
    expect($patient->age)->toBe(30)
        ->and($patient->age_unit)->toBe('year');

    $this->actingAs($staff)
        ->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertSee('https://lab.mohsinmedicalcomplex.com/invoice/1', false);
});

it('maps non-binary patient gender using the configured fallback for the external API', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([], 201),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);
    Config::set('hms.lab_cases.fallback_gender', 'female');

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
        'name' => 'Other Patient',
        'gender' => 'other',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => '2704',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 500,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03005556677')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 28)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request['gender'] === 'female'
        && $request['test_codes'] === [2704]
        && $request['age'] === 28
        && $request['age_unit'] === 'Year');
});

it('does not call the external lab API when the invoice only has outsourced tests', function () {
    Http::fake();

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03004445566']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Outsourced Only',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => 'OUT-ONLY',
        'sourcing' => LabTestSourcing::Outsourced,
        'price' => 200,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03004445566')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertNothingSent();
});

it('sends only in-house test codes when the invoice mixes in-house and outsourced tests', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([], 201),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03003332211']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Mixed Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $inHouse = LabTest::factory()->create([
        'test_code' => 'IN-1',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 300,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);
    $outsourced = LabTest::factory()->create([
        'test_code' => 'OUT-1',
        'sourcing' => LabTestSourcing::Outsourced,
        'price' => 400,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03003332211')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$inHouse->id, $outsourced->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request['test_codes'] === ['IN-1']);
});

it('persists a lab API log when the external API returns a non-success status', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response(['error' => 'server'], 502),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

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
        'name' => 'Error Log Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => 'ERR-1',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03009998877')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('kind', InvoiceKind::Lab)->latest('id')->first();
    expect($invoice)->not->toBeNull();

    $log = LabApiRequestLog::query()->where('invoice_id', $invoice->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->succeeded)->toBeFalse()
        ->and($log->response_status)->toBe(502)
        ->and($log->error_message)->toBeNull();
});

it('retries POST lab-cases when the lab returns 429 then succeeds', function () {
    Config::set('hms.lab_cases.retry_delays_ms', [1, 1, 1]);

    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::sequence()
            ->push(['message' => 'Too Many'], 429)
            ->push(['message' => 'Lab case created.'], 201),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03007778899']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Retry Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => '1509',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 200,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03007778899')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertSentCount(2);
});

it('sends receipt_no instead of invoice_number when configured', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([], 201),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);
    Config::set('hms.lab_cases.receipt_reference', 'receipt_no');
    Config::set('hms.lab_cases.invoice_number_prefix', 'R-');

    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03006665544']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Receipt Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => '88',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03006665544')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('kind', InvoiceKind::Lab)->latest('id')->first();
    expect($invoice)->not->toBeNull();

    Http::assertSent(function ($request) use ($invoice) {
        return isset($request['receipt_no'])
            && $request['receipt_no'] === 'R-'.$invoice->id
            && ! isset($request['invoice_number'])
            && $request['test_codes'] === [88];
    });
});

it('truncates patient name and phone to lab API limits', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([], 201),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $longPhone = str_repeat('0', 25);
    $family = Family::query()->create(['phone' => $longPhone]);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => str_repeat('N', 150),
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => '1',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 50,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', $longPhone)
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        $name = (string) ($request['name'] ?? '');
        $phone = (string) ($request['phone'] ?? '');

        return strlen($name) === 100
            && strlen($phone) === 20
            && $request['test_codes'] === [1]
            && $request['age'] === 30
            && $request['age_unit'] === 'Year';
    });
});

it('persists a lab API log row when the lab returns 422 with missing_test_codes', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([
            'message' => 'One or more test codes were not found.',
            'missing_test_codes' => [9999, 8888],
        ], 422),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03005554433']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => '422 Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => '42',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03005554433')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 30)
        ->set('patientAgeUnit', 'year')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('kind', InvoiceKind::Lab)->latest('id')->first();
    expect($invoice)->not->toBeNull();

    $log = LabApiRequestLog::query()->where('invoice_id', $invoice->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->response_status)->toBe(422)
        ->and($log->succeeded)->toBeFalse()
        ->and((string) $log->response_body)->toContain('missing_test_codes');
});

it('sends Month as age_unit when lab checkout uses months', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([], 201),
    ]);

    Config::set('hms.lab_cases.api_token', 'test-bearer-token');
    Config::set('hms.lab_cases.api_url', 'https://lab.mohsinmedicalcomplex.com');
    Config::set('hms.lab_cases.sync_enabled', true);

    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $family = Family::query()->create(['phone' => '03001110000']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Infant Patient',
        'gender' => 'female',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $lt = LabTest::factory()->create([
        'test_code' => '99',
        'sourcing' => LabTestSourcing::InHouse,
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03001110000')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('patientAge', 6)
        ->set('patientAgeUnit', 'month')
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request['age'] === 6 && $request['age_unit'] === 'Month');

    $patient->refresh();
    expect($patient->age)->toBe(6)
        ->and($patient->age_unit)->toBe('month');
});
