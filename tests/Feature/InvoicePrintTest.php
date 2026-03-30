<?php

use App\Enums\InvoiceStatus;
use App\Enums\PatientType;
use App\Enums\QueueResetType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
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

uses(RefreshDatabase::class);

test('guest is redirected from invoice print', function () {
    $this->get(route('invoices.print', ['invoice' => 99]))
        ->assertRedirect(route('login'));
});

test('staff can view invoice print page', function () {
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

    $service = Service::query()->create([
        'name' => 'Consult',
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

    $invoice = Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'total_amount' => 500,
        'discount' => 0,
        'final_amount' => 500,
        'status' => InvoiceStatus::Paid,
    ]);

    InvoiceService::query()->create([
        'invoice_id' => $invoice->id,
        'service_id' => $service->id,
        'service_price_id' => $sp->id,
        'doctor_id' => null,
        'price' => 500,
        'doctor_share_amount' => 0,
        'discount' => 0,
        'final_amount' => 500,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('MMC', false)
        ->assertSee('Test Patient', false)
        ->assertSee('Thank you', false);
});
