<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceKind;
use App\Enums\UserRole;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShiftCloseSummaryPrintController extends Controller
{
    public function __invoke(Shift $shift): View
    {
        if (! config('hms.skip_role_page_guards')) {
            $role = Auth::user()?->role;
            if (! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
                abort(403);
            }
        }

        $tz = config('app.timezone', 'UTC');

        $shift->load([
            'opener',
            'closer',
            'expenses' => fn ($q) => $q->orderBy('id'),
        ]);

        $openingBalance = (int) $shift->opening_balance;
        $totalOpdInvoicesPaid = $shift->totalPaidInvoicesForKind(InvoiceKind::Opd);
        $totalLabInvoicesPaid = $shift->totalPaidInvoicesForKind(InvoiceKind::Lab);
        $totalProcedureInvoicesPaid = $shift->totalPaidInvoicesForKind(InvoiceKind::Procedure);
        $totalInvoices = $shift->totalInvoices();
        $totalDoctorPayouts = $shift->totalDoctorPayouts();
        $totalExpenses = $shift->totalExpenses();
        $netAmount = $shift->netAmount();
        $doctorPayouts = $shift->doctorPayoutBreakdownByDoctor();

        return view('shifts.print-close-summary', [
            'clinicName' => config('hms.clinic_name', 'HMS'),
            'shift' => $shift,
            'tz' => $tz,
            'openingBalance' => $openingBalance,
            'totalOpdInvoicesPaid' => $totalOpdInvoicesPaid,
            'totalLabInvoicesPaid' => $totalLabInvoicesPaid,
            'totalProcedureInvoicesPaid' => $totalProcedureInvoicesPaid,
            'totalInvoices' => $totalInvoices,
            'totalDoctorPayouts' => $totalDoctorPayouts,
            'totalExpenses' => $totalExpenses,
            'netAmount' => $netAmount,
            'doctorPayouts' => $doctorPayouts,
            'printedAt' => now()->timezone($tz),
        ]);
    }
}
