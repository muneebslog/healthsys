<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Paid doctor share amounts grouped by doctor (same window and rules as totalDoctorPayouts()).
     *
     * @return Collection<int, object{doctor_name: string, total_share: int}>
     */
    public function doctorPayoutBreakdownByDoctor(): Collection
    {
        $from = $this->opened_at ?? $this->created_at ?? now()->startOfDay();
        $to = $this->closed_at ?? now();
        $invoiceIds = $this->invoices()->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return collect();
        }

        $invoiceServices = (new InvoiceService)->getTable();

        return DB::table($invoiceServices)
            ->join('doctor_share_ledger_items', 'doctor_share_ledger_items.invoice_service_id', '=', $invoiceServices.'.id')
            ->join('doctor_share_ledger', 'doctor_share_ledger.id', '=', 'doctor_share_ledger_items.ledger_id')
            ->join('users', 'users.id', '=', 'doctor_share_ledger.paid_by')
            ->join('doctors', 'doctors.id', '=', $invoiceServices.'.doctor_id')
            ->whereIn($invoiceServices.'.invoice_id', $invoiceIds)
            ->where('users.role', UserRole::Staff)
            ->whereBetween('doctor_share_ledger.paid_at', [$from, $to])
            ->selectRaw('doctors.name as doctor_name, SUM('.$invoiceServices.'.doctor_share_amount) as total_share')
            ->groupBy('doctors.id', 'doctors.name')
            ->orderByDesc('total_share')
            ->get()
            ->map(fn (object $row): object => (object) [
                'doctor_name' => $row->doctor_name,
                'total_share' => (int) $row->total_share,
            ]);
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
