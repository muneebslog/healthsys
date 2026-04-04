<?php

use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\QueueResetType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Doctor;
use App\Models\DoctorShareLedger;
use App\Models\DoctorShareLedgerItem;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
use App\Services\DoctorSharePayoutReceiptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest is redirected from doctor share payout receipt', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $doctor = Doctor::query()->create([
        'name' => 'Dr Guest Ledger',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);
    $ledger = DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 0,
        'paid_at' => now(),
        'notes' => null,
    ]);

    $this->get(route('reception.doctor-share-payout-receipt', $ledger))
        ->assertRedirect(route('login'));
});

test('staff can open doctor share payout receipt', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $family = Family::query()->create(['phone' => '03001112233']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Receipt Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Receipt',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'first_five_slips_full_share' => false,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'Consult R',
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
        'doctor_share_paid' => true,
    ]);

    $ledger = DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 700,
        'paid_at' => now(),
        'notes' => null,
    ]);

    DoctorShareLedgerItem::query()->create([
        'ledger_id' => $ledger->id,
        'invoice_service_id' => $line->id,
    ]);

    $this->actingAs($staff)
        ->get(route('reception.doctor-share-payout-receipt', $ledger))
        ->assertSuccessful()
        ->assertSee(__('Doctor share payout'), escape: false)
        ->assertSee('700', escape: false);
});

test('receipt builder splits first five full share slips and base slips', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $family = Family::query()->create(['phone' => '03004445566']);
    $patient = Patient::query()->create([
        'family_id' => $family->id,
        'name' => 'Split Patient',
        'gender' => 'male',
        'type' => PatientType::Head,
        'relation_to_head' => null,
    ]);
    $family->update(['head_id' => $patient->id]);

    $doctor = Doctor::query()->create([
        'name' => 'Dr Split',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'first_five_slips_full_share' => true,
        'user_id' => null,
    ]);

    $service = Service::query()->create([
        'name' => 'Consult S',
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

    $lines = [];
    for ($i = 0; $i < 6; $i++) {
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

        $shareAmount = $i < 5 ? 1000 : 700;
        $lines[] = InvoiceService::query()->create([
            'invoice_id' => $invoice->id,
            'service_id' => $service->id,
            'service_price_id' => $sp->id,
            'doctor_id' => $doctor->id,
            'price' => 1000,
            'doctor_share_amount' => $shareAmount,
            'discount' => 0,
            'final_amount' => 1000,
            'doctor_share_paid' => true,
        ]);
    }

    $ledger = DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 5 * 1000 + 700,
        'paid_at' => now(),
        'notes' => null,
    ]);

    foreach ($lines as $line) {
        DoctorShareLedgerItem::query()->create([
            'ledger_id' => $ledger->id,
            'invoice_service_id' => $line->id,
        ]);
    }

    $ledger->load('items');
    $data = DoctorSharePayoutReceiptBuilder::fromLedger($ledger);

    expect($data->fullShareSlipCount)->toBe(5)
        ->and($data->baseShareSlipCount)->toBe(1)
        ->and($data->fullShareSubtotal)->toBe(5000)
        ->and($data->baseShareSubtotal)->toBe(700)
        ->and($data->totalShare)->toBe(5700)
        ->and($data->fullShareDistinctInvoiceCount)->toBe(5)
        ->and($data->baseShareDistinctInvoiceCount)->toBe(1);
});
