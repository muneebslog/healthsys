<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\DoctorShareLedger;
use App\Models\User;

test('total paid today sums ledger batches paid between start and end of day', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $doctor = Doctor::query()->create([
        'name' => 'Dr Ledger',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 3_000,
        'paid_at' => now()->subDay(),
        'notes' => null,
    ]);

    DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 7_000,
        'paid_at' => now(),
        'notes' => null,
    ]);

    expect(DoctorShareLedger::totalPaidToday())->toBe(7_000);
});

test('sums by doctor paid today groups multiple batches for the same doctor', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $doctorA = Doctor::query()->create([
        'name' => 'Dr A',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);
    $doctorB = Doctor::query()->create([
        'name' => 'Dr B',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    DoctorShareLedger::query()->create([
        'doctor_id' => $doctorA->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 1_000,
        'paid_at' => now(),
        'notes' => null,
    ]);
    DoctorShareLedger::query()->create([
        'doctor_id' => $doctorA->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 2_500,
        'paid_at' => now(),
        'notes' => null,
    ]);
    DoctorShareLedger::query()->create([
        'doctor_id' => $doctorB->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 500,
        'paid_at' => now(),
        'notes' => null,
    ]);

    $rows = DoctorShareLedger::sumsByDoctorPaidToday();

    expect($rows)->toHaveCount(2)
        ->and($rows->first()->doctor_name)->toBe('Dr A')
        ->and($rows->first()->total_share)->toBe(3_500)
        ->and($rows->last()->doctor_name)->toBe('Dr B')
        ->and($rows->last()->total_share)->toBe(500);
});

test('staff reception shifts page shows today doctor payout section', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $doctor = Doctor::query()->create([
        'name' => 'Dr Reception Banner',
        'specialization' => 'GP',
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);

    DoctorShareLedger::query()->create([
        'doctor_id' => $doctor->id,
        'paid_by' => $staff->id,
        'period_from' => now()->toDateString(),
        'period_to' => now()->toDateString(),
        'total_share' => 9_999,
        'paid_at' => now(),
        'notes' => null,
    ]);

    $this->actingAs($staff)
        ->get(route('reception.shifts'))
        ->assertOk()
        ->assertSee('9,999', false)
        ->assertSee('Dr Reception Banner', false);
});
