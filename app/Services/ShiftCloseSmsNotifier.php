<?php

namespace App\Services;

use App\Enums\InvoiceKind;
use App\Models\Shift;
use Illuminate\Support\Facades\Log;

class ShiftCloseSmsNotifier
{
    public function __construct(
        private VeevoTechSmsService $sms,
    ) {}

    /**
     * Notify owner + finance manager (when configured) with shift close summary.
     */
    public function notifyClosedShift(int $shiftId): void
    {
        $phones = $this->recipientPhones();

        if ($phones === []) {
            return;
        }

        $shift = Shift::query()
            ->whereKey($shiftId)
            ->with(['opener', 'closer', 'expenses'])
            ->first();

        if (! $shift || ! $shift->closed_at) {
            return;
        }

        $text = $this->buildMessage($shift);

        foreach ($phones as $digits) {
            $ok = $this->sms->sendToStoredPhone($digits, $text);

            if (! $ok) {
                Log::info('[HMS] Shift close SMS not sent or skipped', [
                    'shift_id' => $shiftId,
                ]);
            }
        }
    }

    /**
     * @return list<string> Unique raw digit strings suitable for sendToStoredPhone.
     */
    private function recipientPhones(): array
    {
        $owner = trim((string) config('hms.sms.shift_close_owner_phone', ''));
        $finance = trim((string) config('hms.sms.shift_close_finance_manager_phone', ''));

        $seen = [];
        $out = [];

        foreach ([$owner, $finance] as $raw) {
            if ($raw === '') {
                continue;
            }

            $norm = $this->sms->normalizePakistanDigitsToE164($raw);

            if ($norm === null) {
                Log::warning('[HMS] Shift close SMS: invalid phone in config', [
                    'len' => strlen(preg_replace('/\D/', '', $raw) ?? ''),
                ]);

                continue;
            }

            if (isset($seen[$norm])) {
                continue;
            }

            $seen[$norm] = true;
            $out[] = $raw;
        }

        return $out;
    }

    private function buildMessage(Shift $shift): string
    {
        $clinic = (string) config('hms.clinic_name', 'HMS');
        $tz = config('app.timezone', 'UTC');

        $open = $shift->opened_at->timezone($tz)->format('d M Y H:i');
        $close = $shift->closed_at->timezone($tz)->format('d M Y H:i');

        $opening = (int) $shift->opening_balance;
        $opdSales = $shift->totalPaidInvoicesForKind(InvoiceKind::Opd);
        $labSales = $shift->totalPaidInvoicesForKind(InvoiceKind::Lab);
        $doc = $shift->totalDoctorPayouts();
        $exp = $shift->totalExpenses();
        $net = $shift->netAmount();

        $opener = $shift->opener?->name ?? '?';
        $closer = $shift->closer?->name ?? '?';

        $fmt = static fn (int $n): string => number_format($n);

        return "{$clinic} Shift #{$shift->id} closed. Open {$fmt($opening)}, OPD {$fmt($opdSales)}, Lab {$fmt($labSales)}, Doc {$fmt($doc)}, Exp {$fmt($exp)}, Net {$fmt($net)}. Opened {$open} by {$opener}. Closed {$close} by {$closer}.";
    }
}
