<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#07090c">
    <title>{{ config('hms.clinic_name', 'HMS') }} — Token display</title>
    <link rel="stylesheet" href="{{ asset('css/token-screen.css') }}?v=4">
</head>
<body class="ts-body">
    <div id="ts-root" class="ts-root">
        <section id="ts-picker" class="ts-panel ts-picker" aria-label="Select queue" hidden>
            <header class="ts-header ts-picker-head">
                <h1 class="ts-clinic">{{ config('hms.clinic_name', 'Clinic') }}</h1>
                <p class="ts-sub">Select a queue to display</p>
            </header>
            <div id="ts-picker-cards" class="ts-picker-cards"></div>
            <p id="ts-picker-empty" class="ts-muted ts-center" hidden>No active queues right now.</p>
            <p id="ts-picker-error" class="ts-error ts-center" hidden></p>
        </section>

        <section id="ts-display" class="ts-panel ts-display" aria-live="polite" aria-atomic="true" hidden>
            <header class="ts-display-head">
                <p id="ts-doctor" class="ts-doctor">&nbsp;</p>
                <p id="ts-service" class="ts-service">&nbsp;</p>
            </header>
            <p class="ts-now-label">Now serving</p>
            <p id="ts-patient" class="ts-patient" hidden></p>
            <div id="ts-token" class="ts-token" role="status">—</div>
            <p class="ts-waiting">Waiting: <span id="ts-waiting-count">0</span></p>
            <p id="ts-display-error" class="ts-error ts-center" hidden></p>
        </section>
    </div>

    <nav id="ts-kiosk" class="ts-kiosk" aria-label="Queue controls" hidden>
        <button type="button" id="ts-btn-prev" class="ts-kiosk-btn" title="Previous">&#9664;</button>
        <button type="button" id="ts-btn-skip" class="ts-kiosk-btn" title="Skip">&#9166;</button>
        <button type="button" id="ts-btn-next" class="ts-kiosk-btn" title="Call next">&#9654;</button>
    </nav>

    <script>
        window.HMS_TOKEN_SCREEN = {
            pollMs: 4000,
            controlSecret: @json(config('hms.token_screen_control_secret')),
            controlsEnabled: @json(auth()->check() || filled(config('hms.token_screen_control_secret'))),
            urls: {
                queues: @json(url('/api/token-screen/queues')),
                dataBase: @json(url('/api/token-screen/data')),
                callNext: @json(url('/api/queues/__QUEUE__/call-next')),
                skip: @json(url('/api/queues/__QUEUE__/skip')),
                previous: @json(url('/api/queues/__QUEUE__/previous')),
            }
        };
    </script>
    <script src="{{ asset('js/token-screen.js') }}?v=3"></script>
</body>
</html>
