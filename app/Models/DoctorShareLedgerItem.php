<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorShareLedgerItem extends Model
{
    protected $fillable = ['ledger_id', 'invoice_service_id'];

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(DoctorShareLedger::class, 'ledger_id');
    }

    public function invoiceService(): BelongsTo
    {
        return $this->belongsTo(InvoiceService::class);
    }
}
