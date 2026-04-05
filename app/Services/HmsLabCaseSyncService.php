<?php

namespace App\Services;

use App\Enums\InvoiceKind;
use App\Enums\LabTestSourcing;
use App\Models\Invoice;
use App\Models\LabApiRequestLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class HmsLabCaseSyncService
{
    private const int MAX_STORED_RESPONSE_BYTES = 65536;

    /**
     * POST lab case to the external lab HMS (POST /api/hms/lab-cases).
     * Only in-house tests (invoice lines with sourcing in_house) are sent; outsourced tests are omitted.
     * Failures are logged only; local checkout is never rolled back.
     */
    public function pushForInvoiceId(int $invoiceId): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $invoice = Invoice::query()
            ->whereKey($invoiceId)
            ->where('kind', InvoiceKind::Lab)
            ->with(['patient.family', 'labTests'])
            ->first();

        if (! $invoice || $invoice->labTests->isEmpty()) {
            return;
        }

        $patient = $invoice->patient;
        if (! $patient) {
            return;
        }

        $inHouseLines = $invoice->labTests->filter(
            fn ($line): bool => $line->sourcing === LabTestSourcing::InHouse
        );

        if ($inHouseLines->isEmpty()) {
            return;
        }

        $testCodes = $inHouseLines
            ->pluck('test_code')
            ->map(fn (mixed $c): string => trim((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($testCodes === []) {
            Log::warning('hms_lab_case_sync_skipped_empty_test_codes', ['invoice_id' => $invoiceId]);

            return;
        }

        $payload = [
            'name' => $this->normalizePatientNameForLabApi((string) $patient->name),
            'gender' => $this->normalizeGender((string) $patient->gender),
            'test_codes' => array_values(array_map(
                fn (string $code): int|string => $this->normalizeTestCodeForLabApi($code),
                $testCodes
            )),
        ];

        $this->applyReceiptReferenceToPayload($payload, $this->invoiceNumber($invoice));

        $phone = $patient->family?->phone;
        if (filled($phone)) {
            $normalizedPhone = $this->normalizePhoneForLabApi((string) $phone);
            if ($normalizedPhone !== '') {
                $payload['phone'] = $normalizedPhone;
            }
        }

        if ($patient->age !== null) {
            $payload['age'] = (int) $patient->age;
            $payload['age_unit'] = $patient->age_unit === 'month' ? 'Month' : 'Year';
        }

        $url = $this->endpoint();
        $startedAt = hrtime(true);

        try {
            $response = $this->httpForLabCases()->post($url, $payload);

            $this->persistOutboundLabApiLog($invoiceId, $url, $payload, $response, null, $startedAt);

            if ($response->successful()) {
                return;
            }

            $this->logLabCaseHttpFailure($invoiceId, $response);
        } catch (\Throwable $e) {
            $this->persistOutboundLabApiLog($invoiceId, $url, $payload, null, $e, $startedAt);

            Log::error('hms_lab_case_sync_exception', [
                'invoice_id' => $invoiceId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function httpForLabCases(): PendingRequest
    {
        $delays = config('hms.lab_cases.retry_delays_ms');

        if (! is_array($delays) || $delays === []) {
            $delays = [500, 1500, 3000];
        }

        return Http::timeout($this->timeoutSeconds())
            ->withToken($this->token())
            ->acceptJson()
            ->asJson()
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

    private function normalizePatientNameForLabApi(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return 'Unknown';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed, 0, 100, 'UTF-8');
        }

        return substr($trimmed, 0, 100);
    }

    private function normalizePhoneForLabApi(string $phone): string
    {
        $trimmed = trim($phone);

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed, 0, 20, 'UTF-8');
        }

        return substr($trimmed, 0, 20);
    }

    /**
     * Lab API: test_codes must match tests.code; numeric codes may be sent as integers (guide).
     */
    private function normalizeTestCodeForLabApi(string $code): int|string
    {
        $trimmed = trim($code);

        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return (int) $trimmed;
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyReceiptReferenceToPayload(array &$payload, string $referenceValue): void
    {
        $field = (string) config('hms.lab_cases.receipt_reference', 'invoice_number');

        if (! in_array($field, ['invoice_number', 'receipt_no'], true)) {
            $field = 'invoice_number';
        }

        if ($field === 'receipt_no') {
            $payload['receipt_no'] = $referenceValue;

            return;
        }

        $payload['invoice_number'] = $referenceValue;
    }

    private function logLabCaseHttpFailure(int $invoiceId, Response $response): void
    {
        $context = [
            'invoice_id' => $invoiceId,
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        if ($response->status() === 422) {
            $json = $response->json();
            if (is_array($json)) {
                $context['missing_test_codes'] = $json['missing_test_codes'] ?? null;
                $context['message'] = $json['message'] ?? null;
            }
        }

        Log::warning('hms_lab_case_sync_http_error', $context);
    }

    private function persistOutboundLabApiLog(
        int $invoiceId,
        string $url,
        array $requestPayload,
        ?Response $response,
        ?\Throwable $exception,
        int|float|false $startedAt,
    ): void {
        $durationMs = null;

        if ($startedAt !== false) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        }

        $succeeded = $exception === null && $response !== null && $response->successful();

        LabApiRequestLog::query()->create([
            'invoice_id' => $invoiceId,
            'method' => 'POST',
            'url' => $url,
            'request_headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer [redacted]',
            ],
            'request_body' => $requestPayload,
            'response_status' => $response?->status(),
            'response_body' => $response !== null ? $this->truncateResponseBody($response->body()) : null,
            'succeeded' => $succeeded,
            'error_message' => $exception?->getMessage(),
            'duration_ms' => $durationMs,
        ]);
    }

    private function truncateResponseBody(string $body): string
    {
        if (strlen($body) <= self::MAX_STORED_RESPONSE_BYTES) {
            return $body;
        }

        return substr($body, 0, self::MAX_STORED_RESPONSE_BYTES)."\n… [truncated]";
    }

    private function isConfigured(): bool
    {
        if (! config('hms.lab_cases.sync_enabled')) {
            return false;
        }

        return filled(config('hms.lab_cases.api_token')) && filled(config('hms.lab_cases.api_url'));
    }

    private function endpoint(): string
    {
        $base = rtrim((string) config('hms.lab_cases.api_url'), '/');

        return $base.'/api/hms/lab-cases';
    }

    private function token(): string
    {
        return (string) config('hms.lab_cases.api_token');
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('hms.lab_cases.timeout'));
    }

    private function invoiceNumber(Invoice $invoice): string
    {
        $prefix = (string) config('hms.lab_cases.invoice_number_prefix');

        return $prefix !== ''
            ? $prefix.$invoice->id
            : (string) $invoice->id;
    }

    private function normalizeGender(string $gender): string
    {
        $g = strtolower($gender);

        if (in_array($g, ['male', 'female'], true)) {
            return $g;
        }

        return (string) config('hms.lab_cases.fallback_gender');
    }
}
