<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VeevoTechSmsService
{
    private const string SEND_URL = 'https://api.veevotech.com/v3/sendsms';

    /**
     * Send SMS using stored family phone (11-digit local PK format, digits only).
     */
    public function sendToStoredPhone(string $phoneDigits, string $textMessage): bool
    {
        $receiver = $this->normalizePakistanDigitsToE164($phoneDigits);

        if ($receiver === null) {
            Log::info('[HMS] SMS skipped: invalid or empty phone', [
                'phone_digits_len' => strlen(preg_replace('/\D/', '', $phoneDigits) ?? ''),
            ]);

            return false;
        }

        return $this->send($receiver, $textMessage);
    }

    /**
     * POST JSON: hash, receivernum, sendernum, textmessage.
     */
    public function send(string $receiverInternational, string $textMessage): bool
    {
        if (! config('hms.sms.enabled')) {
            return false;
        }

        $hash = (string) config('hms.sms.hash', '');

        if ($hash === '') {
            Log::warning('[HMS] SMS enabled but VEEVOTECH / hms.sms.hash is empty');

            return false;
        }

        $sender = (string) config('hms.sms.sender', 'Default');

        try {
            $response = Http::timeout((int) config('hms.sms.timeout', 15))
                ->asJson()
                ->acceptJson()
                ->post(self::SEND_URL, [
                    'hash' => $hash,
                    'receivernum' => $receiverInternational,
                    'sendernum' => $sender,
                    'textmessage' => $textMessage,
                ]);
        } catch (\Throwable $e) {
            Log::error('[HMS] VeevoTech SMS request failed', [
                'exception' => $e->getMessage(),
                'receiver' => $receiverInternational,
            ]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::warning('[HMS] VeevoTech SMS rejected', [
            'status' => $response->status(),
            'body' => $response->body(),
            'receiver' => $receiverInternational,
        ]);

        return false;
    }

    /**
     * Convert 11-digit local mobile (e.g. 03XXXXXXXXX) to +92… for the API.
     */
    public function normalizePakistanDigitsToE164(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+92'.substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            return '+92'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '92')) {
            return '+'.$digits;
        }

        return null;
    }
}
