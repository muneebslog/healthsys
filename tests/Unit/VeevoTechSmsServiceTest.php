<?php

use App\Services\VeevoTechSmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'hms.sms.enabled' => true,
        'hms.sms.hash' => 'test-hash',
        'hms.sms.sender' => 'MySender',
        'hms.sms.timeout' => 10,
    ]);
});

it('returns false when SMS is disabled without calling the API', function () {
    config(['hms.sms.enabled' => false]);

    Http::fake();

    $svc = new VeevoTechSmsService;

    expect($svc->sendToStoredPhone('03123456789', 'Hello'))->toBeFalse();

    Http::assertNothingSent();
});

it('posts JSON to VeevoTech when sending', function () {
    Http::fake([
        'api.veevotech.com/*' => Http::response(['ok' => true], 200),
    ]);

    $svc = new VeevoTechSmsService;

    expect($svc->sendToStoredPhone('03123456789', 'Hello world'))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.veevotech.com/v3/sendsms'
            && $request->method() === 'POST'
            && $request['hash'] === 'test-hash'
            && $request['receivernum'] === '+923123456789'
            && $request['sendernum'] === 'MySender'
            && $request['textmessage'] === 'Hello world';
    });
});

it('normalizes Pakistan 11-digit local numbers to E.164', function () {
    $svc = new VeevoTechSmsService;

    expect($svc->normalizePakistanDigitsToE164('03123456789'))->toBe('+923123456789')
        ->and($svc->normalizePakistanDigitsToE164('3123456789'))->toBe('+923123456789')
        ->and($svc->normalizePakistanDigitsToE164('923123456789'))->toBe('+923123456789')
        ->and($svc->normalizePakistanDigitsToE164(''))->toBeNull()
        ->and($svc->normalizePakistanDigitsToE164('123'))->toBeNull();
});
