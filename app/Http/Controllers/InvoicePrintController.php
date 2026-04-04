<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceKind;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\VisitService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        if (! config('hms.skip_role_page_guards')) {
            $role = Auth::user()?->role;
            if (! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
                abort(403);
            }
        }

        $invoice->load(['patient.family', 'services.service', 'services.doctor', 'labTests', 'procedure.doctor']);

        if ($invoice->kind === InvoiceKind::Lab) {
            $rows = $invoice->labTests->sortBy('id')->values()->map(function ($line, int $idx) {
                $label = $line->test_code.' — '.$line->test_name;

                return [
                    'service' => $label,
                    'doctor' => __('Lab'),
                    'price' => (int) $line->line_final_amount,
                    'token_number' => null,
                    'token_prefix' => $idx < 26 ? chr(65 + $idx) : 'S'.($idx + 1),
                ];
            });

            $discountAmount = (int) $invoice->discount;
            $showRxHandwritingBlock = false;
        } elseif ($invoice->kind === InvoiceKind::Procedure) {
            $proc = $invoice->procedure;
            $line = __('Procedure payment');
            if ($proc !== null) {
                $line .= ' — '.$proc->operation_name.' · '.__('Ref').' '.$proc->reference_number;
            }

            $rows = collect([
                [
                    'service' => $line,
                    'doctor' => $proc?->doctor?->name ?? '—',
                    'price' => (int) $invoice->final_amount,
                    'token_number' => null,
                    'token_prefix' => 'A',
                ],
            ]);

            $discountAmount = 0;
            $showRxHandwritingBlock = false;
        } else {
            $visitServices = VisitService::query()
                ->where('visit_id', $invoice->visit_id)
                ->orderBy('id')
                ->with('queueToken')
                ->get();

            $lines = $invoice->services->sortBy('id')->values();

            $rows = $lines->map(function ($is, int $idx) use ($visitServices) {
                $vs = $visitServices->get($idx);

                return [
                    'service' => $is->service?->name ?? '—',
                    'doctor' => $is->doctor?->name ?? '—',
                    'price' => (int) $is->final_amount,
                    'token_number' => $vs?->queueToken?->token_number,
                    'token_prefix' => $idx < 26 ? chr(65 + $idx) : 'S'.($idx + 1),
                ];
            });

            $invoiceLevelDiscount = (int) $invoice->discount;
            $lineDiscountSum = (int) $invoice->services->sum('discount');
            $discountAmount = $invoiceLevelDiscount > 0 ? $invoiceLevelDiscount : $lineDiscountSum;

            /** @see DatabaseSeeder::seedDefaultServices id 2 = General Checkup — extra slip space for vitals + Rx */
            $showRxHandwritingBlock = $invoice->services->contains(
                fn ($is) => (int) $is->service_id === 2
            );
        }

        return view('invoices.print', [
            'clinicName' => config('hms.clinic_name', 'MMC'),
            'invoice' => $invoice,
            'rows' => $rows,
            'printedAt' => $invoice->created_at->timezone(config('app.timezone')),
            'showDiscountRow' => $discountAmount > 0,
            'discountAmount' => $discountAmount,
            'showRxHandwritingBlock' => $showRxHandwritingBlock,
            'isLabInvoice' => $invoice->kind === InvoiceKind::Lab,
            'isProcedureInvoice' => $invoice->kind === InvoiceKind::Procedure,
        ]);
    }
}
