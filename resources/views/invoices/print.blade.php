<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice') }} #{{ $invoice->id }} — {{ $clinicName }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            font-size: 12px;
            line-height: 1.35;
            color: #111;
            background: #fff;
            padding: 8px 10px 16px;
            max-width: 80mm;
            margin: 0 auto;
        }
        .clinic {
            text-align: center;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .meta {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.45;
            color: #000;
            margin-bottom: 10px;
        }
        .meta strong {
            font-weight: 800;
            font-size: 13px;
        }
        .patient-block {
            text-align: center;
            margin: 12px 0 14px;
            padding: 10px 6px;
            border: 2px solid #111;
            border-radius: 4px;
        }
        .patient-name {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .token-hero {
            font-size: 28px;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .token-stack-item {
            margin-top: 10px;
        }
        .token-stack-item:first-of-type { margin-top: 4px; }
        .token-service-prefix {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #444;
            margin-bottom: 2px;
        }
        .token-hero-stacked {
            font-size: 22px;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .token-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 2px;
            color: #444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 4px;
        }
        th, td {
            padding: 4px 2px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #ccc;
        }
        th {
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #333;
            border-bottom: 2px solid #111;
        }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.doc, th.doc { max-width: 22mm; word-wrap: break-word; }
        .discount-row td {
            font-weight: 600;
            color: #b45309;
            border-bottom: 1px solid #ddd;
        }
        .total-row {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 3px double #111;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
        }
        .total-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .total-amount {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .rx-handwriting-block {
            margin-top: 14px;
            padding-top: 8px;
            font-size: 12px;
            line-height: 1.4;
        }
        .rx-vitals-row {
            display: flex;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 10px;
        }
        .rx-vitals-field {
            display: flex;
            align-items: baseline;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        .rx-vitals-field span:first-child {
            white-space: nowrap;
        }
        .rx-vitals-line {
            flex: 1;
            border-bottom: 1px dotted #999;
            min-height: 1.2em;
        }
        .rx-heading {
            font-weight: 700;
            margin-bottom: 6px;
        }
        .rx-line {
            border-bottom: 1px dotted #bbb;
            min-height: 1.35em;
            margin-bottom: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px dashed #999;
            font-size: 12px;
            font-weight: 600;
            color: #333;
        }
        .lab-qr-hero {
            text-align: center;
        }
        .lab-qr-hero img {
            display: block;
            margin: 4px auto 6px;
            width: 120px;
            height: 120px;
            image-rendering: pixelated;
        }
        .lab-qr-url {
            font-size: 8px;
            line-height: 1.3;
            word-break: break-all;
            color: #333;
            max-width: 100%;
        }
        .sample-slip {
            page-break-before: always;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px dashed #333;
        }
        .sample-slip .clinic { margin-bottom: 4px; }
        .sample-slip-title {
            text-align: center;
            font-weight: 800;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .sample-slip-sub {
            text-align: center;
            font-size: 9px;
            color: #444;
            margin-bottom: 10px;
        }
        .sample-serial-wrap {
            text-align: center;
            margin: 10px 0 12px;
            padding: 10px 6px;
            border: 2px solid #111;
            border-radius: 4px;
        }
        .sample-serial-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #444;
            margin-bottom: 4px;
        }
        .sample-serial-num {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
        }
        .sample-patient-grid {
            font-size: 11px;
            line-height: 1.45;
            margin-bottom: 10px;
            padding: 8px 6px;
            background: #f7f7f7;
            border-radius: 4px;
        }
        .sample-patient-grid dt {
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #555;
            margin-top: 6px;
        }
        .sample-patient-grid dt:first-child { margin-top: 0; }
        .sample-patient-grid dd { margin: 0; }
        .sample-tests-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-top: 4px;
        }
        .sample-tests-table th,
        .sample-tests-table td {
            padding: 4px 2px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #ccc;
        }
        .sample-tests-table th {
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #333;
            border-bottom: 2px solid #111;
        }
        .sample-tests-table td.num { text-align: right; white-space: nowrap; }
        @media print {
            body { padding: 4px 6px; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="clinic">{{ $clinicName }}</div>
    <div class="meta">
        <strong>{{ __('Invoice') }} #{{ $invoice->id }}</strong><br>
        <strong>{{ $printedAt->format('d M Y') }} · {{ $printedAt->format('g:i A') }}</strong>
    </div>

    @php
        $multiService = $rows->count() > 1;
        $rowsWithToken = $rows->filter(fn ($r) => filled($r['token_number'] ?? null));
        $labQrInHero = ($isLabInvoice ?? false)
            && ! empty($labCaseQrDataUri ?? null)
            && ! empty($labCaseInvoiceUrl ?? null);
    @endphp

    <div class="patient-block">
        <div class="patient-name">{{ $invoice->patient?->name ?? '—' }}</div>
        @if ($labQrInHero)
            <div class="token-label">{{ __('Lab portal') }}</div>
            <div class="lab-qr-hero">
                <img src="{{ $labCaseQrDataUri }}" width="120" height="120" alt="{{ __('QR code') }}" />
                <div class="lab-qr-url">{{ $labCaseInvoiceUrl }}</div>
            </div>
        @else
            <div class="token-label">{{ ($isLabInvoice ?? false) ? __('Lab portal') : __('Token') }}</div>
            @if ($isLabInvoice ?? false)
                <div class="token-hero">—</div>
            @elseif ($rowsWithToken->isEmpty())
                <div class="token-hero">—</div>
            @elseif (! $multiService || $rowsWithToken->count() === 1)
                <div class="token-hero">{{ $rowsWithToken->first()['token_number'] }}</div>
            @else
                @foreach ($rows as $row)
                    @if (filled($row['token_number'] ?? null))
                        <div class="token-stack-item">
                            <div class="token-service-prefix">{{ $row['token_prefix'] }} · {{ $row['service'] }}</div>
                            <div class="token-hero-stacked">{{ $row['token_number'] }}</div>
                        </div>
                    @endif
                @endforeach
            @endif
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th class="num">{{ __('Token') }}</th>
                <th>
                    @if ($isLabInvoice ?? false)
                        {{ __('Test') }}
                    @elseif ($isProcedureInvoice ?? false)
                        {{ __('Procedure') }}
                    @else
                        {{ __('Service') }}
                    @endif
                </th>
                <th class="doc">{{ ($isLabInvoice ?? false) ? __('Source') : __('Doctor') }}</th>
                <th class="num">{{ __('Price') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="num tabular-nums">
                        @if (filled($row['token_number'] ?? null))
                            @if ($multiService)
                                {{ $row['token_prefix'] }}·{{ $row['token_number'] }}
                            @else
                                {{ $row['token_number'] }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $row['service'] }}</td>
                    <td class="doc">{{ $row['doctor'] }}</td>
                    <td class="num">{{ number_format($row['price']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($showDiscountRow)
        <table style="margin-top: 6px;">
            <tbody>
                <tr class="discount-row">
                    <td colspan="3">{{ __('Discount') }}</td>
                    <td class="num">−{{ number_format($discountAmount) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="total-row">
        <span class="total-label">{{ __('Total') }}</span>
        <span class="total-amount">{{ number_format((int) $invoice->final_amount) }}</span>
    </div>

    @if ($showRxHandwritingBlock ?? false)
        <div class="rx-handwriting-block" aria-hidden="true">
            <div class="rx-vitals-row">
                <div class="rx-vitals-field">
                    <span>temp:</span>
                    <span class="rx-vitals-line"></span>
                </div>
                <div class="rx-vitals-field">
                    <span>BP:</span>
                    <span class="rx-vitals-line"></span>
                </div>
            </div>
            <div class="rx-heading">Rx.</div>
            @for ($i = 0; $i < 8; $i++)
                <div class="rx-line"></div>
            @endfor
        </div>
    @endif

    <div class="footer">{{ __('Thank you') }}</div>

    @if (($isLabInvoice ?? false) && filled($invoice->lab_sample_slip_serial))
        @php
            $slipPatient = $invoice->patient;
            $ageStr = $slipPatient?->age !== null
                ? (string) (int) $slipPatient->age.' '.($slipPatient->age_unit === 'month' ? __('months') : __('years'))
                : '—';
        @endphp
        <section class="sample-slip" aria-label="{{ __('Lab sample slip') }}">
            <div class="clinic">{{ $clinicName }}</div>
            <div class="sample-slip-title">{{ __('Lab sample slip') }}</div>
            <div class="sample-slip-sub">{{ __('Attach to specimen — no pricing') }}</div>
            <div class="meta" style="margin-bottom: 8px;">
                <strong>{{ $printedAt->format('d M Y') }} · {{ $printedAt->format('g:i A') }}</strong><br>
                <strong>{{ __('Receipt ref.') }} #{{ $invoice->id }}</strong>
            </div>
            <div class="sample-serial-wrap">
                <div class="sample-serial-label">{{ __('Sample serial') }}</div>
                <div class="sample-serial-num">{{ number_format((int) $invoice->lab_sample_slip_serial) }}</div>
            </div>
            <dl class="sample-patient-grid">
                <dt>{{ __('Patient') }}</dt>
                <dd>{{ $slipPatient?->name ?? '—' }}</dd>
                <dt>{{ __('Gender') }}</dt>
                <dd>{{ $slipPatient?->gender ? ucfirst((string) $slipPatient->gender) : '—' }}</dd>
                <dt>{{ __('Age') }}</dt>
                <dd>{{ $ageStr }}</dd>
                <dt>{{ __('Phone') }}</dt>
                <dd>{{ $slipPatient?->family?->phone ?? '—' }}</dd>
            </dl>
            <table class="sample-tests-table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Test') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th class="num">{{ __('Days') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($labSampleSlipLines as $slipLine)
                        <tr>
                            <td>{{ $slipLine['test_code'] }}</td>
                            <td>{{ $slipLine['test_name'] }}</td>
                            <td>{{ $slipLine['sourcing_label'] }}</td>
                            <td class="num">{{ $slipLine['days_required'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 200);
            window.addEventListener('afterprint', function () {
                window.close();
            });
        });
    </script>
</body>
</html>
