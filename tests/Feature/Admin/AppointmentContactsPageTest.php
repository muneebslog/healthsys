<?php

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Family;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

test('guests cannot access admin appointment contacts', function () {
    $this->get(route('admin.appointment-contacts'))
        ->assertRedirect(route('login'));
});

test('staff cannot access admin appointment contacts', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($user)
        ->get(route('admin.appointment-contacts'))
        ->assertForbidden();
});

test('admin sees family phone for consultation appointment when doctor is selected', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $creator = User::factory()->create();
    $doctor = Doctor::factory()->create(['status' => 'active']);
    $family = Family::factory()->create(['phone' => '03001112233']);
    $patient = Patient::factory()->head()->create(['family_id' => $family->id]);
    $family->refresh();

    Appointment::query()->create([
        'patient_id' => $patient->id,
        'family_id' => $family->id,
        'doctor_id' => $doctor->id,
        'service_id' => 1,
        'queue_token_id' => null,
        'created_by' => $creator->id,
        'appointment_date' => now()->addDay()->toDateString(),
        'appointment_time' => '10:00:00',
        'status' => AppointmentStatus::Booked,
        'notes' => null,
        'sms_sent' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.appointment-contacts')
        ->set('doctorId', (string) $doctor->id)
        ->assertSee('03001112233')
        ->assertSee(__('Call'))
        ->assertSee(__('Copy'));
});
