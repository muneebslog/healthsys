<?php

namespace App\Services;

use App\Enums\InvoiceKind;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class HmsLabCaseSyncService
{
    /**
     * POST lab case to the external HMS lab app (e.g. lab.mohsinmedicalcomplex.com).
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

        $testCodes = $invoice->labTests
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
            'name' => $patient->name,
            'invoice_number' => $this->invoiceNumber($invoice),
            'gender' => $this->normalizeGender((string) $patient->gender),
            'test_codes' => $testCodes,
        ];

        $phone = $patient->family?->phone;
        if (filled($phone)) {
            $payload['phone'] = (string) $phone;
        }

        $url = $this->endpoint();

        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->withToken($this->token())
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if ($response->successful()) {
                return;
            }

            Log::warning('hms_lab_case_sync_http_error', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('hms_lab_case_sync_exception', [
                'invoice_id' => $invoiceId,
                'message' => $e->getMessage(),
            ]);
        }
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
