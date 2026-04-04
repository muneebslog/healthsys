<?php

use App\Models\LabTest;
use Database\Seeders\LabTestsFromJsonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports lab tests from json and is idempotent on rerun', function (): void {
    $this->seed(LabTestsFromJsonSeeder::class);

    $firstCount = LabTest::count();
    expect($firstCount)->toBeGreaterThan(0);
    expect(LabTest::whereNull('test_code')->exists())->toBeTrue();

    $this->seed(LabTestsFromJsonSeeder::class);

    expect(LabTest::count())->toBe($firstCount);
});
