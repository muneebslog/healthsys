<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        <title>{{ config('app.name', 'HealthSys') }} — {{ __('Welcome') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600|syne:600,700,800" rel="stylesheet" />

        <style>
            :root {
                --bg-deep: #0c1412;
                --bg-mid: #12231f;
                --accent: #2dd4bf;
                --accent-dim: rgba(45, 212, 191, 0.14);
                --accent-glow: rgba(45, 212, 191, 0.35);
                --cream: #f4f1ea;
                --cream-muted: #a8b5b0;
                --panel: rgba(18, 35, 31, 0.72);
                --panel-border: rgba(255, 255, 255, 0.08);
                --font-display: 'Syne', ui-sans-serif, system-ui, sans-serif;
                --font-body: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
            }

            @media (prefers-color-scheme: light) {
                :root {
                    --bg-deep: #e8ebe9;
                    --bg-mid: #dce3df;
                    --accent: #0d9488;
                    --accent-dim: rgba(13, 148, 136, 0.12);
                    --accent-glow: rgba(13, 148, 136, 0.22);
                    --cream: #0f1714;
                    --cream-muted: #4a5c55;
                    --panel: rgba(255, 255, 255, 0.78);
                    --panel-border: rgba(15, 23, 20, 0.08);
                }
            }

            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

            html {
                scroll-behavior: smooth;
            }

            body {
                min-height: 100vh;
                font-family: var(--font-body);
                font-size: 1rem;
                line-height: 1.6;
                color: var(--cream);
                background: var(--bg-deep);
                overflow-x: hidden;
            }

            .welcome-bg {
                position: fixed;
                inset: 0;
                z-index: 0;
                background:
                    radial-gradient(ellipse 120% 80% at 10% 20%, rgba(45, 212, 191, 0.12) 0%, transparent 50%),
                    radial-gradient(ellipse 90% 60% at 90% 80%, rgba(20, 184, 166, 0.08) 0%, transparent 45%),
                    linear-gradient(165deg, var(--bg-deep) 0%, var(--bg-mid) 100%);
            }

            @media (prefers-color-scheme: light) {
                .welcome-bg {
                    background:
                        radial-gradient(ellipse 120% 80% at 10% 20%, rgba(13, 148, 136, 0.15) 0%, transparent 50%),
                        radial-gradient(ellipse 90% 60% at 90% 80%, rgba(15, 118, 110, 0.1) 0%, transparent 45%),
                        linear-gradient(165deg, var(--bg-deep) 0%, #eef2f0 100%);
                }
            }

            .welcome-bg::after {
                content: '';
                position: absolute;
                inset: 0;
                opacity: 0.04;
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
                pointer-events: none;
            }

            .grid-overlay {
                position: fixed;
                inset: 0;
                z-index: 0;
                background-image:
                    linear-gradient(var(--panel-border) 1px, transparent 1px),
                    linear-gradient(90deg, var(--panel-border) 1px, transparent 1px);
                background-size: 64px 64px;
                mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, black 20%, transparent 70%);
                opacity: 0.5;
                pointer-events: none;
            }

            .wrap {
                position: relative;
                z-index: 1;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                padding: clamp(1.25rem, 4vw, 2.5rem);
                max-width: 1200px;
                margin: 0 auto;
            }

            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: clamp(2rem, 8vh, 4rem);
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                text-decoration: none;
                color: inherit;
            }

            .brand-mark {
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 0.65rem;
                background: linear-gradient(135deg, var(--accent) 0%, #14b8a6 100%);
                display: grid;
                place-items: center;
                box-shadow: 0 8px 32px var(--accent-glow);
            }

            .brand-mark svg {
                width: 1.35rem;
                height: 1.35rem;
                color: var(--bg-deep);
            }

            @media (prefers-color-scheme: light) {
                .brand-mark svg { color: #fff; }
            }

            .brand-text {
                font-family: var(--font-display);
                font-weight: 700;
                font-size: 1.125rem;
                letter-spacing: -0.02em;
            }

            .brand-sub {
                font-size: 0.7rem;
                font-weight: 500;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--cream-muted);
            }

            .nav-actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
                gap: 0.5rem 0.75rem;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.6rem 1.15rem;
                font-family: var(--font-body);
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
                border-radius: 0.5rem;
                border: 1px solid transparent;
                cursor: pointer;
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
            }

            .btn:focus-visible {
                outline: 2px solid var(--accent);
                outline-offset: 3px;
            }

            .btn-ghost {
                color: var(--cream);
                background: transparent;
                border-color: var(--panel-border);
            }

            .btn-ghost:hover {
                background: var(--accent-dim);
                border-color: rgba(45, 212, 191, 0.35);
            }

            @media (prefers-color-scheme: light) {
                .btn-ghost:hover {
                    border-color: rgba(13, 148, 136, 0.35);
                }
            }

            .btn-primary {
                color: var(--bg-deep);
                background: var(--accent);
                border-color: var(--accent);
                box-shadow: 0 4px 24px var(--accent-glow);
            }

            .btn-primary:hover {
                transform: translateY(-1px);
                filter: brightness(1.05);
            }

            @media (prefers-color-scheme: light) {
                .btn-primary {
                    color: #fff;
                }
            }

            .hero {
                flex: 1;
                display: grid;
                grid-template-columns: 1fr;
                gap: clamp(2rem, 5vw, 3.5rem);
                align-items: center;
            }

            @media (min-width: 900px) {
                .hero {
                    grid-template-columns: 1.1fr 0.9fr;
                }
            }

            .hero-copy h1 {
                font-family: var(--font-display);
                font-weight: 800;
                font-size: clamp(2.25rem, 5.5vw, 3.75rem);
                line-height: 1.05;
                letter-spacing: -0.03em;
                margin-bottom: 1.25rem;
            }

            .hero-copy h1 span {
                color: var(--accent);
            }

            .hero-lead {
                font-size: 1.0625rem;
                color: var(--cream-muted);
                max-width: 32ch;
                margin-bottom: 2rem;
            }

            .cta-row {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                align-items: center;
            }

            .btn-tv {
                color: var(--cream);
                background: var(--panel);
                border: 1px solid var(--panel-border);
                backdrop-filter: blur(12px);
            }

            .btn-tv:hover {
                border-color: rgba(45, 212, 191, 0.4);
                background: var(--accent-dim);
            }

            .btn-tv svg {
                flex-shrink: 0;
                opacity: 0.9;
            }

            .hero-card {
                position: relative;
                padding: clamp(1.5rem, 3vw, 2rem);
                border-radius: 1.25rem;
                background: var(--panel);
                border: 1px solid var(--panel-border);
                backdrop-filter: blur(16px);
                box-shadow: 0 24px 80px rgba(0, 0, 0, 0.25);
            }

            @media (prefers-color-scheme: light) {
                .hero-card {
                    box-shadow: 0 24px 80px rgba(15, 23, 20, 0.08);
                }
            }

            .hero-card::before {
                content: '';
                position: absolute;
                top: -1px;
                left: 2rem;
                right: 2rem;
                height: 3px;
                border-radius: 0 0 4px 4px;
                background: linear-gradient(90deg, transparent, var(--accent), transparent);
                opacity: 0.7;
            }

            .stat-row {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
                margin-bottom: 1.5rem;
                padding-bottom: 1.5rem;
                border-bottom: 1px solid var(--panel-border);
            }

            .stat {
                text-align: center;
            }

            .stat-val {
                font-family: var(--font-display);
                font-weight: 700;
                font-size: 1.5rem;
                color: var(--accent);
            }

            .stat-label {
                font-size: 0.7rem;
                font-weight: 500;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: var(--cream-muted);
                margin-top: 0.25rem;
            }

            .card-note {
                font-size: 0.875rem;
                color: var(--cream-muted);
                line-height: 1.55;
            }

            .card-note strong {
                color: var(--cream);
                font-weight: 600;
            }

            .foot {
                margin-top: auto;
                padding-top: 2.5rem;
                font-size: 0.8rem;
                color: var(--cream-muted);
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem 1.5rem;
                justify-content: space-between;
                align-items: center;
            }

            .foot a {
                color: var(--accent);
                text-decoration: none;
                font-weight: 500;
            }

            .foot a:hover {
                text-decoration: underline;
            }

            @media (prefers-reduced-motion: reduce) {
                .btn, html {
                    transition: none;
                    scroll-behavior: auto;
                }
                .btn-primary:hover {
                    transform: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="welcome-bg" aria-hidden="true"></div>
        <div class="grid-overlay" aria-hidden="true"></div>

        <div class="wrap">
            <header class="topbar">
                <a href="{{ route('home') }}" class="brand">
                    <span class="brand-mark" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </span>
                    <span>
                        <span class="brand-text">{{ config('hms.clinic_name', config('app.name')) }}</span>
                        <span class="brand-sub block">{{ config('app.name') }}</span>
                    </span>
                </a>

                @if (Route::has('login'))
                    <nav class="nav-actions" aria-label="{{ __('Primary') }}">
                        @auth
                            <a class="btn btn-primary" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                        @else
                            <a class="btn btn-ghost" href="{{ route('login') }}">{{ __('Log in') }}</a>
                            @if (Route::has('register'))
                                <a class="btn btn-primary" href="{{ route('register') }}">{{ __('Register') }}</a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <main class="hero">
                <div class="hero-copy">
                    <h1>
                        {{ __('Care that') }} <span>{{ __('flows') }}</span>,<br>
                        {{ __('from desk to ward') }}.
                    </h1>
                    <p class="hero-lead">
                        {{ __('Hospital operations, queues, and visits in one place. Sign in for reception and clinical tools, or open the wall display for patients.') }}
                    </p>
                    <div class="cta-row">
                        @auth
                            <a class="btn btn-primary" href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</a>
                        @else
                            @if (Route::has('login'))
                                <a class="btn btn-primary" href="{{ route('login') }}">{{ __('Staff sign in') }}</a>
                            @endif
                        @endauth
                        <a class="btn btn-tv" href="{{ route('token-screen') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{ __('TV token display') }}
                        </a>
                    </div>
                </div>

                <aside class="hero-card" aria-labelledby="card-heading">
                    <div class="stat-row">
                        <div class="stat">
                            <div class="stat-val">∞</div>
                            <div class="stat-label">{{ __('Queues') }}</div>
                        </div>
                        <div class="stat">
                            <div class="stat-val">T-##</div>
                            <div class="stat-label">{{ __('Tokens') }}</div>
                        </div>
                        <div class="stat">
                            <div class="stat-val">24/7</div>
                            <div class="stat-label">{{ __('Display') }}</div>
                        </div>
                    </div>
                    <p class="card-note" id="card-heading">
                        <strong>{{ __('Token screen') }}</strong>
                        — {{ __('Full-screen queue board for TVs and kiosks. Bookmark this URL on your waiting-room display; staff controls stay in the app.') }}
                    </p>
                </aside>
            </main>

            <footer class="foot">
                <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
                <span>
                    <a href="{{ route('token-screen') }}">{{ __('Open token screen') }}</a>
                </span>
            </footer>
        </div>
    </body>
</html>
