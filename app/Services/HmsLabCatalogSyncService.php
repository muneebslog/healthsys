<?php

namespace App\Services;

use App\Enums\LabTestSourcing;
use App\Models\LabTest;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches the lab HMS test catalog (GET /api/hms/tests) and optionally imports rows into {@see LabTest}.
 */
final class HmsLabCatalogSyncService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchCatalogTests(): array
    {
        if (! $this->hasApiCredentials()) {
            throw new \RuntimeException('Lab API URL and token must be configured (HMS_LAB_CASES_API_URL, HMS_LAB_CASES_API_TOKEN).');
        }

        $url = $this->catalogUrl();
        $response = $this->http()->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf(
                'Lab catalog request failed (%d): %s',
                $response->status(),
                $response->body()
            ));
        }

        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        $tests = $json['tests'] ?? null;

        return is_array($tests) ? array_values($tests) : [];
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importCatalog(bool $dryRun, bool $updatePrices): array
    {
        $rows = $this->fetchCatalogTests();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;

                continue;
            }

            $code = trim((string) ($row['code'] ?? ''));

            if ($code === '') {
                $skipped++;

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Test '.$code;
            }

            $price = max(0, (int) round((float) ($row['price'] ?? 0)));

            $existing = LabTest::query()->where('test_code', $code)->first();

            if ($dryRun) {
                if ($existing) {
                    if ($updatePrices) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $created++;
                }

                continue;
            }

            if ($existing) {
                if ($updatePrices) {
                    $existing->update([
                        'name' => $name,
                        'price' => $price,
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            LabTest::query()->create([
                'name' => $name,
                'test_code' => $code,
                'sourcing' => LabTestSourcing::InHouse->value,
                'days_required' => 0,
                'price' => $price,
                'hospital_share' => 70,
                'lab_share' => 30,
                'is_active' => true,
            ]);
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function hasApiCredentials(): bool
    {
        return filled(config('hms.lab_cases.api_token')) && filled(config('hms.lab_cases.api_url'));
    }

    private function catalogUrl(): string
    {
        $base = rtrim((string) config('hms.lab_cases.api_url'), '/');

        return $base.'/api/hms/tests';
    }

    private function http(): PendingRequest
    {
        $delays = config('hms.lab_cases.retry_delays_ms');

        if (! is_array($delays) || $delays === []) {
            $delays = [500, 1500, 3000];
        }

        return Http::timeout(max(1, (int) config('hms.lab_cases.timeout')))
            ->withToken((string) config('hms.lab_cases.api_token'))
            ->acceptJson()
            ->retry(
                $delays,
                0,
                function (\Throwable $exception): bool {
                    if (! $exception instanceof RequestException) {
                        return false;
                    }

                    return (int) ($exception->response?->status()) === 429;
                },
                false
            );
    }
}
