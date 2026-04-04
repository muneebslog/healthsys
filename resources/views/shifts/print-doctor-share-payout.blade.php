<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Doctor share payout') }} #{{ $receipt->ledgerId }} — {{ $clinicName }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-monospace, 'Cascadia Mono', 'Consolas', monospace;
            font-size: 11px;
            line-height: 1.45;
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
        .hint {
            font-size: 8px;
            color: #666;
            margin-top: 6px;
            line-height: 1.35;
        }
        @media print {
            body { padding: 4px 6px; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="clinic">{{ $clinicName }}</div>
    <div class="title">{{ __('Doctor share payout') }}</div>
    <div class="meta">
        <strong>#{{ $receipt->ledgerId }}</strong><br>
        {{ __('Paid') }} {{ $receipt->paidAt->format('d M Y g:i A') }}
        @if ($receipt->paidByName)
            <br>{{ __('By') }} {{ $receipt->paidByName }}
        @endif
        <br>
        {{ __('Doctor') }}: <strong>{{ $receipt->doctorName }}</strong>
        <br>
        {{ __('Period') }}: {{ \Illuminate\Support\Carbon::parse($receipt->periodFrom)->format('M j, Y') }}
        —
        {{ \Illuminate\Support\Carbon::parse($receipt->periodTo)->format('M j, Y') }}
    </div>

    <div class="section">
        <div class="section-h">{{ __('Share breakdown') }}</div>
        <div class="row">
            <span class="lbl">{{ __('Full share slips') }}</span>
            <span class="num">{{ number_format($receipt->fullShareSlipCount) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('Invoices (full share)') }}</span>
            <span class="num">{{ number_format($receipt->fullShareDistinctInvoiceCount) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('Base share slips') }}</span>
            <span class="num">{{ number_format($receipt->baseShareSlipCount) }}</span>
        </div>
        <div class="row">
            <span class="lbl">{{ __('Invoices (base share)') }}</span>
            <span class="num">{{ number_format($receipt->baseShareDistinctInvoiceCount) }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-h">{{ __('Amounts') }}</div>
        @if ($receipt->fullShareSlipCount > 0)
            <div class="row">
                <span class="lbl">{{ __('Full share subtotal') }}</span>
                <span class="num">{{ number_format($receipt->fullShareSubtotal) }}</span>
            </div>
            <div class="row">
                <span class="lbl">{{ __('Avg per full share slip') }}</span>
                <span class="num">{{ $receipt->avgFullSharePerSlip !== null ? number_format($receipt->avgFullSharePerSlip) : '—' }}</span>
            </div>
        @endif
        @if ($receipt->baseShareSlipCount > 0)
            <div class="row">
                <span class="lbl">{{ __('Base share subtotal') }}</span>
                <span class="num">{{ number_format($receipt->baseShareSubtotal) }}</span>
            </div>
            <div class="row">
                <span class="lbl">{{ __('Avg per base share slip') }}</span>
                <span class="num">{{ $receipt->avgBaseSharePerSlip !== null ? number_format($receipt->avgBaseSharePerSlip) : '—' }}</span>
            </div>
            @if ($receipt->baseSharePercentOfLine !== null)
                <div class="row">
                    <span class="lbl">{{ __('Base share % of line (weighted)') }}</span>
                    <span class="num">{{ $receipt->baseSharePercentOfLine }}%</span>
                </div>
            @endif
        @endif
        <div class="net">
            <span class="lbl">{{ __('Total share') }}</span>
            <span class="num">{{ number_format($receipt->totalShare) }}</span>
        </div>
    </div>

    @if ($receipt->notes)
        <div class="section">
            <div class="section-h">{{ __('Notes') }}</div>
            <p class="hint" style="font-size:10px;font-style:normal;color:#222;">{{ $receipt->notes }}</p>
        </div>
    @endif

    <p class="hint">{{ __('Slips are invoice lines (one line per service on an invoice). “Full share” uses the first five slips of the day when enabled for this doctor.') }}</p>

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
