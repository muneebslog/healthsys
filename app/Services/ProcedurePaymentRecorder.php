<?php

namespace App\Services;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Shift;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class ProcedurePaymentRecorder
{
    public function record(Procedure $procedure, Shift $shift, int $amount, ?string $paymentNote = null): Invoice
    {
        return DB::transaction(function () use ($procedure, $shift, $amount, $paymentNote): Invoice {
            $procedure = Procedure::query()->lockForUpdate()->findOrFail($procedure->id);
            $patient = Patient::query()
                ->with('family')
                ->lockForUpdate()
                ->findOrFail($procedure->patient_id);

            $visit = Visit::query()->create([
                'patient_id' => $patient->id,
                'family_id' => $patient->family_id,
                'doctor_id' => $procedure->doctor_id,
                'shift_id' => $shift->id,
                'status' => VisitStatus::InProgress,
            ]);

            return Invoice::query()->create([
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'shift_id' => $shift->id,
                'procedure_id' => $procedure->id,
                'kind' => InvoiceKind::Procedure,
                'total_amount' => $amount,
                'discount' => 0,
                'discount_percent' => null,
                'final_amount' => $amount,
                'status' => InvoiceStatus::Paid,
                'payment_note' => $paymentNote,
            ]);
        });
    }
}
