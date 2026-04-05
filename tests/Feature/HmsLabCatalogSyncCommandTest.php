<?php

use App\Models\LabTest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('imports lab tests from GET /api/hms/tests', function () {
    Http::fake([
        'https://lab.example.com/api/hms/tests' => Http::response([
            'tests' => [
                [
                    'id' => 17,
                    'code' => '1300',
                    'name' => 'Complete Blood Count',
                    'price' => '700',
                    'department' => 'Hematology',
                ],
            ],
        ], 200),
    ]);

    Config::set('hms.lab_cases.api_url', 'https://lab.example.com');
    Config::set('hms.lab_cases.api_token', 'secret-token');

    $this->artisan('hms:sync-lab-catalog')->assertSuccessful();

    $row = LabTest::query()->where('test_code', '1300')->first();

    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Complete Blood Count')
        ->and((int) $row->price)->toBe(700);
});

it('does not persist rows when --dry-run is passed', function () {
    LabTest::query()->where('test_code', '7777')->delete();

    Http::fake([
        'https://lab.example.com/api/hms/tests' => Http::response([
            'tests' => [
                ['id' => 1, 'code' => '7777', 'name' => 'Dry run test', 'price' => '100'],
            ],
        ], 200),
    ]);

    Config::set('hms.lab_cases.api_url', 'https://lab.example.com');
    Config::set('hms.lab_cases.api_token', 'secret-token');

    $this->artisan('hms:sync-lab-catalog', ['--dry-run' => true])->assertSuccessful();

    expect(LabTest::query()->where('test_code', '7777')->exists())->toBeFalse();
});
