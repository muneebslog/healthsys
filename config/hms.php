<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Skip role-based page guards (testing)
    |--------------------------------------------------------------------------
    |
    | When true, any authenticated user can open reception and admin Livewire
    | pages and the sidebar shows those links regardless of user role.
    |
    | Set HMS_SKIP_ROLE_PAGE_GUARDS=false in production .env before go-live.
    | PHPUnit sets this to false so role tests stay valid.
    |
    */
    'skip_role_page_guards' => filter_var(
        env('HMS_SKIP_ROLE_PAGE_GUARDS', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Clinic branding (receipts / print)
    |--------------------------------------------------------------------------
    */
    'clinic_name' => env('HMS_CLINIC_NAME', 'MMC'),

    /*
    |--------------------------------------------------------------------------
    | Token screen corner controls (kiosk / old TV browsers)
    |--------------------------------------------------------------------------
    |
    | When set, POST /api/queues/* control routes accept this value in the
    | X-HMS-Control-Secret header (no staff login required on the TV).
    | Leave empty to require an authenticated session for control actions.
    |
    */
    'token_screen_control_secret' => env('TOKEN_SCREEN_CONTROL_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | VeevoTech (VT Network) SMS — https://api.veevotech.com/v3/sendsms
    |--------------------------------------------------------------------------
    |
    | POST JSON: hash, receivernum, sendernum, textmessage
    | Set HMS_SMS_ENABLED=true and VEEVOTECH_SMS_HASH after configuring the account.
    |
    */
    'sms' => [
        'enabled' => filter_var(env('HMS_SMS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'hash' => env('VEEVOTECH_SMS_HASH'),
        'sender' => env('VEEVOTECH_SMS_SENDER', 'Default'),
        'timeout' => (int) env('VEEVOTECH_SMS_TIMEOUT', 15),
        /*
        | Pakistan mobile (11-digit local e.g. 03XXXXXXXXX). Sent on shift close with summary.
        | Both optional; leave empty to skip that recipient. Duplicate numbers are sent once.
        */
        'shift_close_owner_phone' => env('HMS_SHIFT_CLOSE_SMS_OWNER'),
        'shift_close_finance_manager_phone' => env('HMS_SHIFT_CLOSE_SMS_FINANCE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | External lab HMS — catalog + outbound lab case sync
    |--------------------------------------------------------------------------
    |
    | Lab API (HTTPS base URL): Bearer HMS_API_TOKEN, Accept/Content-Type JSON.
    | Endpoints: GET /api/hms/tests (catalog), POST /api/hms/lab-cases (case after billing).
    | Only invoice lines with sourcing in_house are POSTed; outsourced lines are omitted.
    | Sync POST is skipped when the token or URL is empty, when sync_enabled is false, or when there
    | are no in-house lines. Import catalog via: php artisan hms:sync-lab-catalog
    |
    | Rate limit (~60 rpm): 429 responses are retried using retry_delays_ms backoff.
    |
    */
    'lab_cases' => [
        'sync_enabled' => filter_var(
            env('HMS_LAB_CASES_SYNC_ENABLED', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'api_url' => env('HMS_LAB_CASES_API_URL', 'https://lab.mohsinmedicalcomplex.com'),
        'api_token' => env('HMS_LAB_CASES_API_TOKEN'),
        'timeout' => (int) env('HMS_LAB_CASES_API_TIMEOUT', 15),
        'invoice_number_prefix' => env('HMS_LAB_CASES_INVOICE_PREFIX', 'HS-'),
        'fallback_gender' => env('HMS_LAB_CASES_FALLBACK_GENDER', 'male'),
        /*
        | POST body: invoice_number or receipt_no (same value: prefix + local invoice id). Validated in code.
        */
        'receipt_reference' => env('HMS_LAB_CASES_RECEIPT_REFERENCE', 'invoice_number'),
        /*
        | Milliseconds between retries when the lab returns 429 Too Many Requests.
        */
        'retry_delays_ms' => (static function (): array {
            $parsed = array_values(array_filter(
                array_map(
                    static fn (string $v): int => (int) trim($v),
                    explode(',', (string) env('HMS_LAB_CASES_RETRY_DELAYS_MS', '500,1500,3000'))
                ),
                static fn (int $v): bool => $v > 0
            ));

            return $parsed !== [] ? $parsed : [500, 1500, 3000];
        })(),
    ],

];
