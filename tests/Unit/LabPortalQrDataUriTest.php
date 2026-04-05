<?php

use App\Support\LabPortalQrDataUri;

it('returns an svg data uri for the payload', function () {
    $uri = LabPortalQrDataUri::fromUrl('https://lab.example.com/patient/1');

    $b64 = substr($uri, strlen('data:image/svg+xml;base64,'));
    $decoded = base64_decode($b64, true);

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and($decoded)->not->toBeFalse()
        ->and($decoded)->toContain('<svg');
});
