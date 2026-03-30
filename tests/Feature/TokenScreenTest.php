<?php

use App\Enums\PatientType;
use App\Enums\QueueResetType;
use App\Enums\QueueStatus;
use App\Enums\QueueTokenStatus;
use App\Enums\ShiftStatus;
use App\Models\Doctor;
use App\Models\Family;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

/**
 * @return array{user: User, queue: Queue, doctor: Doctor, service: Service}
 */
function seedActiveQueue(): array
{
    $user = User::factory()->create();
    $shift = Shift::create([
        'opened_by' => $user->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);
    $service = Service::create([
        'name' => 'Consultation',
        'is_standalone' => false,
        'reset_type' => QueueResetType::Daily,
        'is_active' => true,
    ]);
    $doctor = Doctor::create([
        'name' => 'Dr. Test',
        'specialization' => null,
        'phone' => null,
        'status' => 'active',
        'is_on_payroll' => false,
        'user_id' => null,
    ]);
    $queue = Queue::create([
        'service_id' => $service->id,
        'doctor_id' => $doctor->id,
        'shift_id' => $shift->id,
        'status' => QueueStatus::Active,
        'current_token' => 2,
        'current_flow_token' => 0,
        'closed_at' => null,
    ]);

    return ['user' => $user, 'queue' => $queue, 'doctor' => $doctor, 'service' => $service];
}

it('lists active queues for the token screen', function () {
    $ctx = seedActiveQueue();

    $this->getJson('/api/token-screen/queues')
        ->assertSuccessful()
        ->assertJsonFragment([
            'queue_id' => $ctx['queue']->id,
            'doctor_name' => $ctx['doctor']->name,
            'service_name' => $ctx['service']->name,
            'remaining_count' => 0,
        ]);
});

it('returns token screen payload for a queue', function () {
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 7,
        'status' => QueueTokenStatus::Serving,
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 8,
        'status' => QueueTokenStatus::Waiting,
    ]);

    $this->getJson('/api/token-screen/data?queue_id='.$queue->id)
        ->assertSuccessful()
        ->assertJsonPath('current_flow_token', 7)
        ->assertJsonPath('patient_name', null)
        ->assertJsonPath('remaining_count', 1);
});

it('includes patient name for the serving token on token screen data', function () {
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    $family = Family::create(['phone' => '0300999888777', 'head_id' => null]);
    $patient = Patient::create([
        'name' => 'Amna Bibi',
        'gender' => 'female',
        'type' => PatientType::Head,
        'relation_to_head' => null,
        'family_id' => $family->id,
    ]);

    QueueToken::create([
        'queue_id' => $queue->id,
        'patient_id' => $patient->id,
        'token_number' => 7,
        'status' => QueueTokenStatus::Serving,
    ]);

    $this->getJson('/api/token-screen/data?queue_id='.$queue->id)
        ->assertSuccessful()
        ->assertJsonPath('patient_name', 'Amna Bibi')
        ->assertJsonPath('current_flow_token', 7);
});

it('validates queue_id on token screen data', function () {
    $this->getJson('/api/token-screen/data')->assertStatus(422);
});

it('returns 404 for inactive queue data', function () {
    $ctx = seedActiveQueue();
    $ctx['queue']->update([
        'status' => QueueStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->getJson('/api/token-screen/data?queue_id='.$ctx['queue']->id)->assertNotFound();
});

it('forbids queue control without auth or kiosk secret', function () {
    config(['hms.token_screen_control_secret' => null]);
    $ctx = seedActiveQueue();

    $this->postJson('/api/queues/'.$ctx['queue']->id.'/call-next')->assertForbidden();
});

it('allows queue control with kiosk secret header', function () {
    config(['hms.token_screen_control_secret' => 'kiosk-test-secret']);
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 1,
        'status' => QueueTokenStatus::Waiting,
    ]);

    $this->postJson('/api/queues/'.$queue->id.'/call-next', [], [
        'X-HMS-Control-Secret' => 'kiosk-test-secret',
    ])->assertSuccessful();

    expect(QueueToken::query()->where('queue_id', $queue->id)->where('status', QueueTokenStatus::Serving)->value('token_number'))->toBe(1);
});

it('allows queue control when authenticated', function () {
    config(['hms.token_screen_control_secret' => null]);
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 3,
        'status' => QueueTokenStatus::Waiting,
    ]);

    $this->actingAs($ctx['user'])->postJson('/api/queues/'.$queue->id.'/call-next')->assertSuccessful();
});

it('call next completes serving and promotes lowest waiting', function () {
    config(['hms.token_screen_control_secret' => 's']);
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 5,
        'status' => QueueTokenStatus::Serving,
        'called_at' => now(),
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 9,
        'status' => QueueTokenStatus::Waiting,
    ]);

    $this->postJson('/api/queues/'.$queue->id.'/call-next', [], [
        'X-HMS-Control-Secret' => 's',
    ])->assertSuccessful();

    expect(QueueToken::query()->where('queue_id', $queue->id)->where('token_number', 5)->value('status'))->toBe(QueueTokenStatus::Done);
    expect(QueueToken::query()->where('queue_id', $queue->id)->where('token_number', 9)->value('status'))->toBe(QueueTokenStatus::Serving);
});

it('skips current serving and calls next waiting', function () {
    config(['hms.token_screen_control_secret' => 's']);
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 2,
        'status' => QueueTokenStatus::Serving,
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 4,
        'status' => QueueTokenStatus::Waiting,
    ]);

    $this->postJson('/api/queues/'.$queue->id.'/skip', [], [
        'X-HMS-Control-Secret' => 's',
    ])->assertSuccessful();

    expect(QueueToken::query()->where('queue_id', $queue->id)->where('token_number', 2)->value('status'))->toBe(QueueTokenStatus::Skipped);
    expect(QueueToken::query()->where('queue_id', $queue->id)->where('token_number', 4)->value('status'))->toBe(QueueTokenStatus::Serving);
});

it('previous restores last done token', function () {
    config(['hms.token_screen_control_secret' => 's']);
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 6,
        'status' => QueueTokenStatus::Done,
        'completed_at' => now(),
    ]);
    QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 7,
        'status' => QueueTokenStatus::Serving,
        'called_at' => now(),
    ]);

    $this->postJson('/api/queues/'.$queue->id.'/previous', [], [
        'X-HMS-Control-Secret' => 's',
    ])->assertSuccessful();

    expect(QueueToken::query()->where('queue_id', $queue->id)->where('token_number', 7)->value('status'))->toBe(QueueTokenStatus::Waiting);
    expect(QueueToken::query()->where('queue_id', $queue->id)->where('token_number', 6)->value('status'))->toBe(QueueTokenStatus::Serving);
});

it('requeues a skipped token', function () {
    config(['hms.token_screen_control_secret' => 's']);
    $ctx = seedActiveQueue();
    $queue = $ctx['queue'];

    $token = QueueToken::create([
        'queue_id' => $queue->id,
        'token_number' => 11,
        'status' => QueueTokenStatus::Skipped,
    ]);

    $this->postJson('/api/tokens/'.$token->id.'/requeue', [], [
        'X-HMS-Control-Secret' => 's',
    ])->assertSuccessful();

    expect($token->fresh()->status)->toBe(QueueTokenStatus::Waiting);
});

it('renders the public token screen page', function () {
    $this->get(route('token-screen'))->assertSuccessful();
});
