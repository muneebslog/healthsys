<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = ['opened_by', 'closed_by', 'opening_balance', 'status', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ShiftExpense::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::Open;
    }

    public function totalInvoices(): int
    {
        return (int) $this->invoices()->where('status', InvoiceStatus::Paid)->sum('final_amount');
    }

    public function totalExpenses(): int
    {
        return (int) $this->expenses()->sum('amount');
    }

    public function totalDoctorPayouts(): int
    {
        $from = $this->opened_at ?? $this->created_at ?? now()->startOfDay();
        $to = $this->closed_at ?? now();

        return (int) InvoiceService::query()
            ->join('doctor_share_ledger_items', 'doctor_share_ledger_items.invoice_service_id', '=', 'invoice_services.id')
            ->join('doctor_share_ledger', 'doctor_share_ledger.id', '=', 'doctor_share_ledger_items.ledger_id')
            ->join('users', 'users.id', '=', 'doctor_share_ledger.paid_by')
            ->whereIn('invoice_services.invoice_id', $this->invoices()->pluck('id'))
            ->where('users.role', UserRole::Staff)
            ->whereBetween('doctor_share_ledger.paid_at', [$from, $to])
            ->sum('invoice_services.doctor_share_amount');
    }

    public function totalDoctorSharesAccrued(): int
    {
        return (int) Invoice::whereIn('invoices.id', $this->invoices()->pluck('id'))
            ->join('invoice_services', 'invoices.id', '=', 'invoice_services.invoice_id')
            ->sum('invoice_services.doctor_share_amount');
    }

    public function netAmount(): int
    {
        return $this->opening_balance + $this->totalInvoices() - $this->totalDoctorPayouts() - $this->totalExpenses();
    }
}
