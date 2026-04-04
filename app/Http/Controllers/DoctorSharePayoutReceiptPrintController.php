<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\DoctorShareLedger;
use App\Services\DoctorSharePayoutReceiptBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DoctorSharePayoutReceiptPrintController extends Controller
{
    public function __invoke(DoctorShareLedger $ledger): View
    {
        if (! config('hms.skip_role_page_guards')) {
            $role = Auth::user()?->role;
            if (! in_array($role, [UserRole::Staff, UserRole::Admin, UserRole::FinanceManager], true)) {
                abort(403);
            }
        }

        $ledger->loadMissing([
            'doctor',
            'paidBy:id,name',
            'items',
        ]);

        $receipt = DoctorSharePayoutReceiptBuilder::fromLedger($ledger);
        $tz = config('app.timezone', 'UTC');

        return view('shifts.print-doctor-share-payout', [
            'clinicName' => config('hms.clinic_name', 'HMS'),
            'receipt' => $receipt,
            'tz' => $tz,
            'printedAt' => now()->timezone($tz),
        ]);
    }
}
