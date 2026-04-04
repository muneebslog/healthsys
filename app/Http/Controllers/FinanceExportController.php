<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceKind;
use App\Enums\UserRole;
use App\Models\DoctorShareLedger;
use App\Models\Invoice;
use App\Models\Shift;
use App\Models\ShiftExpense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()?->role !== UserRole::FinanceManager) {
            abort(403);
        }

        $from = Carbon::parse($request->query('from', now()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        if ($from->greaterThan($to)) {
            abort(422, 'Invalid date range.');
        }

        return match ($type) {
            'invoices' => $this->invoicesCsv($from, $to),
            'expenses' => $this->expensesCsv($from, $to),
            'ledger' => $this->ledgerCsv($from, $to),
            'shifts' => $this->shiftsCsv($from, $to),
            default => abort(404),
        };
    }

    private function invoicesCsv(Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = 'invoices-'.$from->toDateString().'_to_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'patient_id', 'shift_id', 'status', 'total_amount', 'discount', 'final_amount', 'created_at']);

            Invoice::query()
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $inv) {
                        fputcsv($out, [
                            $inv->id,
                            $inv->patient_id,
                            $inv->shift_id,
                            $inv->status->value,
                            $inv->total_amount,
                            $inv->discount,
                            $inv->final_amount,
                            $inv->created_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function expensesCsv(Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = 'shift-expenses-'.$from->toDateString().'_to_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'shift_id', 'created_by', 'label', 'amount', 'created_at']);

            ShiftExpense::query()
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $e) {
                        fputcsv($out, [
                            $e->id,
                            $e->shift_id,
                            $e->created_by,
                            $e->label,
                            $e->amount,
                            $e->created_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function ledgerCsv(Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = 'doctor-share-ledger-'.$from->toDateString().'_to_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'doctor_id', 'paid_by', 'period_from', 'period_to', 'total_share', 'paid_at', 'notes']);

            DoctorShareLedger::query()
                ->whereBetween('paid_at', [$from, $to])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->id,
                            $row->doctor_id,
                            $row->paid_by,
                            $row->period_from?->toDateString(),
                            $row->period_to?->toDateString(),
                            $row->total_share,
                            $row->paid_at?->toIso8601String(),
                            $row->notes,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function shiftsCsv(Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = 'shifts-'.$from->toDateString().'_to_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'status', 'opened_by', 'closed_by', 'opening_balance', 'opened_at', 'closed_at', 'invoices_paid_opd', 'invoices_paid_lab', 'invoices_paid_procedure', 'invoices_paid_total', 'expenses_total', 'doctor_payouts_total', 'net']);

            Shift::query()
                ->where(function ($q) use ($from, $to): void {
                    $q->whereBetween('opened_at', [$from, $to])
                        ->orWhereBetween('closed_at', [$from, $to]);
                })
                ->orderByDesc('opened_at')
                ->chunk(100, function ($rows) use ($out): void {
                    foreach ($rows as $s) {
                        if (! $s instanceof Shift) {
                            continue;
                        }

                        fputcsv($out, [
                            $s->id,
                            $s->status->value,
                            $s->opened_by,
                            $s->closed_by,
                            $s->opening_balance,
                            $s->opened_at?->toIso8601String(),
                            $s->closed_at?->toIso8601String(),
                            $s->totalPaidInvoicesForKind(InvoiceKind::Opd),
                            $s->totalPaidInvoicesForKind(InvoiceKind::Lab),
                            $s->totalPaidInvoicesForKind(InvoiceKind::Procedure),
                            $s->totalInvoices(),
                            $s->totalExpenses(),
                            $s->totalDoctorPayouts(),
                            $s->netAmount(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
