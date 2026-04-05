<?php

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\QueueResetType;
use App\Enums\QueueStatus;
use App\Enums\QueueTokenStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Shift;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows staff to open the invoice lookup page', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($staff)
        ->get(route('reception.invoice-lookup'))
        ->assertSuccessful();
});

it('allows finance manager to open the invoice lookup page', function () {
    $user = User::factory()->create(['role' => UserRole::FinanceManager]);

    $this->actingAs($user)
        ->get(route('reception.invoice-lookup'))
        ->assertSuccessful();
});

it('forbids doctors from opening the invoice lookup page', function () {
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);

    $this->actingAs($doctorUser)
        ->get(route('reception.invoice-lookup'))
        ->assertForbidden();
});

it('validates invoice number on lookup', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Livewire::actingAs($staff)
        ->test('pages::reception.invoice-lookup')
        ->set('invoiceNumber', '')
        ->call('lookup')
        ->assertHasErrors(['invoiceNumber']);
});

it('shows patient service token and queue after lookup', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $shift = Shift::query()->create([
        'opened_by' => $staff->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $patient = Patient::factory()->head()->create(['name' => 'Lookup Patient Alpha']);

    $service = Service::query()->create([
        'name' => 'Consultation Lookup',
        'is_standalone' => true,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);

    $servicePrice = ServicePrice::query()->create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'price' => 400,
        'doctor_share' => 0,
        'hospital_share' => 100,
        'is_active' => true,
    ]);

    $visit = Visit::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $patient->family_id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => VisitStatus::InProgress,
    ]);

    $queue = Queue::query()->create([
        'service_id' => $service->id,
        'doctor_id' => null,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 7,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    $token = QueueToken::query()->create([
        'queue_id' => $queue->id,
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'appointment_id' => null,
        'token_number' => 7,
        'status' => QueueTokenStatus::Waiting,
        'reserved_at' => now(),
        'called_at' => null,
        'completed_at' => null,
        'paid_at' => now(),
    ]);

    VisitService::query()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'doctor_id' => null,
        'service_price_id' => $servicePrice->id,
        'queue_token_id' => $token->id,
        'status' => 'pending',
    ]);

    $invoice = Invoice::query()->create([
        'visit_id' => $visit->id,
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'procedure_id' => null,
        'kind' => InvoiceKind::Opd,
        'total_amount' => 400,
        'discount' => 0,
        'discount_percent' => null,
        'final_amount' => 400,
        'status' => InvoiceStatus::Paid,
        'payment_note' => null,
    ]);

    InvoiceService::query()->create([
        'invoice_id' => $invoice->id,
        'service_id' => $service->id,
        'service_price_id' => $servicePrice->id,
        'doctor_id' => null,
        'price' => 400,
        'doctor_share_amount' => 0,
        'discount' => 0,
        'final_amount' => 400,
        'doctor_share_paid' => false,
    ]);

    $createdLabel = __('Created');
    $createdFormatted = $invoice->fresh()->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A');

    Livewire::actingAs($staff)
        ->test('pages::reception.invoice-lookup')
        ->set('invoiceNumber', (string) $invoice->id)
        ->call('lookup')
        ->assertHasNoErrors()
        ->assertSee('Lookup Patient Alpha')
        ->assertSee('Consultation Lookup')
        ->assertSee('7')
        ->assertSee(__('Queue').' #'.$queue->id, false)
        ->assertSee($createdLabel)
        ->assertSee($createdFormatted, false);
});
