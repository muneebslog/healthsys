<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DoctorShareLedger extends Model
{
    protected $table = 'doctor_share_ledger';

    protected $fillable = ['doctor_id', 'paid_by', 'period_from', 'period_to', 'total_share', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DoctorShareLedgerItem::class, 'ledger_id');
    }

    /**
     * Sum of all ledger batch totals paid today in the application timezone.
     */
    public static function totalPaidToday(): int
    {
        return (int) static::query()
            ->whereBetween('paid_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('total_share');
    }

    /**
     * Per-doctor sums for payouts paid today, highest first.
     *
     * @return Collection<int, object{doctor_name: string, total_share: int}>
     */
    public static function sumsByDoctorPaidToday(): Collection
    {
        $table = (new static)->getTable();

        return DB::table($table)
            ->whereBetween("{$table}.paid_at", [now()->startOfDay(), now()->endOfDay()])
            ->join('doctors', 'doctors.id', '=', "{$table}.doctor_id")
            ->selectRaw('doctors.name as doctor_name, SUM('.$table.'.total_share) as total_share')
            ->groupBy('doctors.id', 'doctors.name')
            ->orderByDesc('total_share')
            ->get()
            ->map(fn (object $row): object => (object) [
                'doctor_name' => $row->doctor_name,
                'total_share' => (int) $row->total_share,
            ]);
    }
}
