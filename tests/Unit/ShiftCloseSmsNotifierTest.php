<?php

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftCloseSmsNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config([
        'hms.sms.enabled' => true,
        'hms.sms.hash' => 'test-hash',
        'hms.sms.sender' => 'MySender',
        'hms.sms.timeout' => 10,
        'hms.clinic_name' => 'MMC',
        'app.timezone' => 'Asia/Karachi',
    ]);
});

it('sends two SMS when owner and finance phones are set', function () {
    config([
        'hms.sms.shift_close_owner_phone' => '03111111111',
        'hms.sms.shift_close_finance_manager_phone' => '03222222222',
    ]);

    Http::fake([
        'api.veevotech.com/*' => Http::response(['ok' => true], 200),
    ]);

    $opener = User::factory()->create(['name' => 'Opener']);
    $closer = User::factory()->create(['name' => 'Closer']);

    $shift = Shift::query()->create([
        'opened_by' => $opener->id,
        'closed_by' => $closer->id,
        'opening_balance' => 1000,
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subHours(2),
        'closed_at' => now(),
    ]);

    app(ShiftCloseSmsNotifier::class)->notifyClosedShift($shift->id);

    Http::assertSentCount(2);
});

it('dedupes identical owner and finance numbers', function () {
    config([
        'hms.sms.shift_close_owner_phone' => '03111111111',
        'hms.sms.shift_close_finance_manager_phone' => '03111111111',
    ]);

    Http::fake([
        'api.veevotech.com/*' => Http::response(['ok' => true], 200),
    ]);

    $opener = User::factory()->create();
    $closer = User::factory()->create();

    $shift = Shift::query()->create([
        'opened_by' => $opener->id,
        'closed_by' => $closer->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Closed,
        'opened_at' => now(),
        'closed_at' => now(),
    ]);

    app(ShiftCloseSmsNotifier::class)->notifyClosedShift($shift->id);

    Http::assertSentCount(1);
});

it('sends nothing when no phones configured', function () {
    config([
        'hms.sms.shift_close_owner_phone' => '',
        'hms.sms.shift_close_finance_manager_phone' => '',
    ]);

    Http::fake();

    $opener = User::factory()->create();
    $closer = User::factory()->create();

    $shift = Shift::query()->create([
        'opened_by' => $opener->id,
        'closed_by' => $closer->id,
        'opening_balance' => 0,
        'status' => ShiftStatus::Closed,
        'opened_at' => now(),
        'closed_at' => now(),
    ]);

    app(ShiftCloseSmsNotifier::class)->notifyClosedShift($shift->id);

    Http::assertNothingSent();
});

it('does not send when shift is not closed', function () {
    config([
        'hms.sms.shift_close_owner_phone' => '03111111111',
    ]);

    Http::fake();

    $opener = User::factory()->create();
    $shift = Shift::query()->create([
        'opened_by' => $opener->id,
        'closed_by' => null,
        'opening_balance' => 0,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
        'closed_at' => null,
    ]);

    app(ShiftCloseSmsNotifier::class)->notifyClosedShift($shift->id);

    Http::assertNothingSent();
});
