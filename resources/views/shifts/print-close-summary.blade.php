<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Shift close summary') }} #{{ $shift->id }} — {{ $clinicName }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-monospace, 'Cascadia Mono', 'Consolas', monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #111;
            background: #fff;
            padding: 8px 10px 16px;
            max-width: 80mm;
            margin: 0 auto;
        }
        .clinic {
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .title {
            text-align: center;
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 6px;
            border-bottom: 2px dashed #333;
            padding-bottom: 6px;
        }
        .meta {
            text-align: center;
            font-size: 9px;
            color: #333;
            margin-bottom: 10px;
        }
        .meta strong { font-weight: 700; }
        .section {
            margin-top: 10px;
        }
        .section-h {
            font-weight: 800;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid #111;
            margin-bottom: 6px;
            padding-bottom: 2px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 3px 0;
            border-bottom: 1px dotted #bbb;
        }
        .row:last-child { border-bottom: none; }
        .row .lbl {
            flex: 1;
            min-width: 0;
            word-wrap: break-word;
        }
        .row .num {
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .row.emph .lbl { font-weight: 700; }
        .row.emph .num { font-weight: 800; }
        .net {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 3px double #111;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
        }
        .net .lbl {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .net .num {
            font-size: 18px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .footer {
            text-align: center;
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px dashed #999;
            font-size: 9px;
            color: #444;
        }
        .empty {
            font-size: 10px;
            color: #666;
            font-style: italic;
            padding: 4px 0;
        }
        @media print {
            body { padding: 4px 6px; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="clinic">{{ $clinicName }}</div>
    <div class="title">{{ __('Shift close summary') }}</div>
    <div class="meta">
        <strong>#{{ $shift->id }}</strong><br>
        @if ($shift->closed_at)
            {{ __('Closed') }} {{ $shift->closed_at->timezone($tz)->format('d M Y g:i A') }}
        @else
            {{ __('Preview') }} · {{ $printedAt->format('d M Y g:i A') }}
        @endif
        <br>
        {{ __('Opened') }} {{ $shift->opened_at->timezone($tz)->format('d M Y g:i A') }}
        @if ($shift->opener)
            · {{ $shift->opener->name }}
        @endif
        @if ($shift->closed_at && $shift->closer)
            <br>{{ __('Closed by') }} {{ $shift->closer->name }}
        @endif
    </div>

    <div class="section">
        <div class="section-h">{{ __('Totals') }}</div>
        <div class="row">
            <span class="lbl">{{ __('Opening balance') }}</span>
            <span class="num">{{ number_format($openingBalance) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('OPD invoices (paid)') }}</span>
            <span class="num">{{ number_format($totalOpdInvoicesPaid) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('Lab invoices (paid)') }}</span>
            <span class="num">{{ number_format($totalLabInvoicesPaid) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('Procedure invoices (paid)') }}</span>
            <span class="num">{{ number_format($totalProcedureInvoicesPaid) }}</span>
        </div>
        <div class="row emph">
            <span class="lbl">{{ __('Total invoices (paid)') }}</span>
            <span class="num">{{ number_format($totalInvoices) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('Doctor payouts (paid out)') }}</span>
            <span class="num">{{ number_format($totalDoctorPayouts) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('Expenses') }}</span>
            <span class="num">{{ number_format($totalExpenses) }}</span>
        </div>
        <div class="net">
            <span class="lbl">{{ __('Net') }}</span>
            <span class="num">{{ number_format($netAmount) }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-h">{{ __('Doctor payouts') }}</div>
        @if ($doctorPayouts->isEmpty())
            <p class="empty">{{ __('None this shift.') }}</p>
        @else
            @foreach ($doctorPayouts as $row)
                <div class="row">
                    <span class="lbl">{{ $row->doctor_name }}</span>
                    <span class="num">{{ number_format($row->total_share) }}</span>
                </div>
            @endforeach
        @endif
    </div>

    <div class="section">
        <div class="section-h">{{ __('Expenses') }}</div>
        @if ($shift->expenses->isEmpty())
            <p class="empty">{{ __('None this shift.') }}</p>
        @else
            @foreach ($shift->expenses as $expense)
                <div class="row">
                    <span class="lbl">{{ $expense->label }}</span>
                    <span class="num">{{ number_format((int) $expense->amount) }}</span>
                </div>
            @endforeach
        @endif
    </div>

    <div class="footer">
        {{ __('Printed') }} {{ $printedAt->format('d M Y g:i A') }}
    </div>

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
