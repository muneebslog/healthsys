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
            font-size: 11px;
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
            font-size: 10px;
            color: #333;
            margin-bottom: 10px;
        }
        .meta strong { font-weight: 700; }
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
            font-size: 10px;
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
            font-size: 9px;
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
        .footer {
            text-align: center;
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px dashed #999;
            font-size: 11px;
            font-weight: 600;
            color: #333;
        }
        @media print {
            body { padding: 4px 6px; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="clinic">{{ $clinicName }}</div>
    <div class="meta">
        <strong>{{ __('Invoice') }} #{{ $invoice->id }}</strong><br>
        {{ $printedAt->format('d M Y') }} · {{ $printedAt->format('g:i A') }}
    </div>

    @php
        $multiService = $rows->count() > 1;
        $rowsWithToken = $rows->filter(fn ($r) => filled($r['token_number'] ?? null));
    @endphp

    <div class="patient-block">
        <div class="patient-name">{{ $invoice->patient?->name ?? '—' }}</div>
        <div class="token-label">{{ __('Token') }}</div>
        @if ($rowsWithToken->isEmpty())
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
    </div>

    <table>
        <thead>
            <tr>
                <th class="num">{{ __('Token') }}</th>
                <th>{{ __('Service') }}</th>
                <th class="doc">{{ __('Doctor') }}</th>
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

    <div class="footer">{{ __('Thank you') }}</div>

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
