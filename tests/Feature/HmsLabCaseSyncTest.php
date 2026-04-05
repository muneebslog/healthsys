<?php

use App\Enums\InvoiceKind;
use App\Enums\PatientType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Family;
use App\Models\Invoice;
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
        'price' => 100,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03001112233')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertNothingSent();
});

it('posts a lab case to the external HMS after checkout when the token is configured', function () {
    Http::fake([
        'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases' => Http::response([
            'message' => 'created',
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
        'test_code' => 'CBC',
        'price' => 1000,
        'hospital_share' => 60,
        'lab_share' => 40,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03001112233')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedTestIds', [$lt->id])
        ->set('discountPercent', 0)
        ->call('createAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->where('kind', InvoiceKind::Lab)->latest('id')->first();

    expect($invoice)->not->toBeNull();

    Http::assertSent(function ($request) use ($invoice) {
        $auth = $request->header('Authorization')[0] ?? '';

        return $request->url() === 'https://lab.mohsinmedicalcomplex.com/api/hms/lab-cases'
            && $auth === 'Bearer test-bearer-token'
            && $request['name'] === 'Sync Patient'
            && $request['phone'] === '03001112233'
            && $request['gender'] === 'female'
            && $request['invoice_number'] === 'HS-'.$invoice->id
            && $request['test_codes'] === ['CBC'];
    });
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
        'test_code' => 'LFT',
        'price' => 500,
        'hospital_share' => 50,
        'lab_share' => 50,
    ]);

    Livewire::actingAs($staff)
        ->test('pages::reception.lab')
        ->set('phoneQuery', '03005556677')
        ->call('lookupPhone')
        ->set('selectedPatientId', $patient->id)
        ->set('selectedTestIds', [$lt->id])
        ->call('createAndPrint')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request['gender'] === 'female');
});
