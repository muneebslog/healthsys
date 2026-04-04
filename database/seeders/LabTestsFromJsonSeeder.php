<?php

namespace Database\Seeders;

use App\Enums\LabTestSourcing;
use App\Models\LabTest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class LabTestsFromJsonSeeder extends Seeder
{
    private const int DEFAULT_HOSPITAL_SHARE = 70;

    private const int DEFAULT_LAB_SHARE = 30;

    public function run(): void
    {
        $path = database_path('data/labtests.json');

        if (! File::isFile($path)) {
            throw new InvalidArgumentException("Missing catalog file: {$path}");
        }

        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (! isset($decoded['tests']) || ! is_array($decoded['tests'])) {
            throw new InvalidArgumentException('Invalid labtests.json: missing "tests" array.');
        }

        foreach ($decoded['tests'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $this->syncRow($row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncRow(array $row): void
    {
        $name = isset($row['test_name']) ? trim((string) $row['test_name']) : '';
        if ($name === '') {
            return;
        }

        $codeRaw = $row['test_code'] ?? null;
        $testCode = $codeRaw !== null && $codeRaw !== '' ? trim((string) $codeRaw) : null;

        $rate = $row['rate'] ?? 0;
        $price = is_int($rate) ? $rate : (int) $rate;

        $payload = [
            'name' => $name,
            'test_code' => $testCode,
            'sourcing' => $this->mapSourcing($row['sourcing'] ?? null),
            'days_required' => $this->daysFromReportsTime($row['reports_time'] ?? null),
            'price' => max(0, $price),
            'hospital_share' => self::DEFAULT_HOSPITAL_SHARE,
            'lab_share' => self::DEFAULT_LAB_SHARE,
            'is_active' => true,
        ];

        if ($testCode !== null) {
            LabTest::query()->updateOrCreate(
                ['test_code' => $testCode],
                $payload
            );

            return;
        }

        $existing = LabTest::query()
            ->whereNull('test_code')
            ->where('name', $name)
            ->first();

        if ($existing) {
            $existing->update($payload);
        } else {
            LabTest::query()->create($payload);
        }
    }

    private function mapSourcing(mixed $value): LabTestSourcing
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';

        return match ($v) {
            'inhouse' => LabTestSourcing::InHouse,
            'outsource' => LabTestSourcing::Outsourced,
            default => LabTestSourcing::Outsourced,
        };
    }

    private function daysFromReportsTime(mixed $value): int
    {
        if (! is_string($value)) {
            return 0;
        }

        $t = strtolower(trim($value));

        if ($t === '' || str_contains($t, 'same day') || $t === 'on lab') {
            return 0;
        }

        if (str_contains($t, 'next day')) {
            return 1;
        }

        if (preg_match('/after\s+(\d+)\s*days?/', $t, $m) === 1) {
            return max(0, min(365, (int) $m[1]));
        }

        return 0;
    }
}
