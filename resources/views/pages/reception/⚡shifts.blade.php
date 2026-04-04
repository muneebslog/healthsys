<?php

use App\Enums\InvoiceKind;
use App\Enums\QueueResetType;
use App\Enums\QueueStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\DoctorShareLedger;
use App\Models\Queue;
use App\Models\Shift;
use App\Models\ShiftExpense;
use App\Services\ShiftCloseSmsNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shift')] class extends Component
{
    public string $openingBalance = '0';

    public string $expenseLabel = '';

    public string $expenseAmount = '';

    public bool $showCloseModal = false;

    public function mount(): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
            abort(403);
        }
    }

    #[Computed]
    public function activeShift(): ?Shift
    {
        return Shift::query()
            ->where('status', ShiftStatus::Open)
            ->with(['opener', 'expenses' => fn ($q) => $q->orderByDesc('id')])
            ->first();
    }

    #[Computed]
    public function recentClosedShifts()
    {
        return Shift::query()
            ->where('status', ShiftStatus::Closed)
            ->with('opener')
            ->orderByDesc('closed_at')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function todaysDoctorPayoutTotal(): int
    {
        return DoctorShareLedger::totalPaidToday();
    }

    #[Computed]
    public function todaysDoctorPayoutByDoctor(): \Illuminate\Support\Collection
    {
        return DoctorShareLedger::sumsByDoctorPaidToday();
    }

    public function openShift(): void
    {
        if ($this->activeShift) {
            $this->addError('shift', __('A shift is already open.'));

            return;
        }

        $validated = $this->validate([
            'openingBalance' => ['required', 'integer', 'min:0'],
        ], [], [
            'openingBalance' => __('opening balance'),
        ]);

        try {
            DB::transaction(function () use ($validated): void {
                if (Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('open_exists');
                }

                $isFirstShiftToday = ! Shift::query()
                    ->whereDate('opened_at', today())
                    ->lockForUpdate()
                    ->exists();

                if ($isFirstShiftToday) {
                    $this->closeActiveQueuesForResetType(QueueResetType::Daily);
                }

                Shift::query()->create([
                    'opened_by' => Auth::id(),
                    'opening_balance' => $validated['openingBalance'],
                    'status' => ShiftStatus::Open,
                    'opened_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'open_exists') {
                $this->addError('shift', __('A shift is already open.'));
            } else {
                throw $e;
            }

            return;
        }

        unset($this->activeShift);
        unset($this->recentClosedShifts);
        $this->openingBalance = '0';
        $this->resetErrorBag();
    }

    public function addExpense(): void
    {
        $shift = $this->activeShift;

        if (! $shift) {
            $this->addError('expense', __('Open a shift before adding expenses.'));

            return;
        }

        $validated = $this->validate([
            'expenseLabel' => ['required', 'string', 'max:255'],
            'expenseAmount' => ['required', 'integer', 'min:1'],
        ], [], [
            'expenseLabel' => __('label'),
            'expenseAmount' => __('amount'),
        ]);

        ShiftExpense::query()->create([
            'shift_id' => $shift->id,
            'created_by' => Auth::id(),
            'label' => $validated['expenseLabel'],
            'amount' => $validated['expenseAmount'],
        ]);

        unset($this->activeShift);
        $this->expenseLabel = '';
        $this->expenseAmount = '';
        $this->resetErrorBag('expenseLabel', 'expenseAmount');
    }

    public function confirmCloseModal(): void
    {
        if (! $this->activeShift) {
            return;
        }

        $this->showCloseModal = true;
    }

    public function closeShift(): void
    {
        $shift = $this->activeShift;

        if (! $shift) {
            $this->showCloseModal = false;

            return;
        }

        $closedShiftId = DB::transaction(function () use ($shift): ?int {
            $locked = Shift::query()
                ->whereKey($shift->id)
                ->where('status', ShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return null;
            }

            $this->closeActiveQueuesForResetType(QueueResetType::PerShift);

            $locked->update([
                'status' => ShiftStatus::Closed,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
            ]);

            return $locked->id;
        });

        $this->showCloseModal = false;
        unset($this->activeShift);
        unset($this->recentClosedShifts);

        if ($closedShiftId !== null) {
            app(ShiftCloseSmsNotifier::class)->notifyClosedShift($closedShiftId);
        }
    }

    protected function closeActiveQueuesForResetType(QueueResetType $type): void
    {
        Queue::query()
            ->whereNull('closed_at')
            ->where('status', '!=', QueueStatus::Finished)
            ->whereHas('service', fn ($q) => $q->where('reset_type', $type))
            ->update([
                'status' => QueueStatus::Closed,
                'closed_at' => now(),
            ]);
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    {{-- Page intro: utilitarian reception desk rhythm, emerald cash-register accent on zinc --}}
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-emerald-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950/20">
        <div class="pointer-events-none absolute -end-16 -top-16 size-48 rounded-full bg-emerald-400/10 blur-3xl dark:bg-emerald-500/10"></div>
        <div class="relative flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Reception shift') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Open with a float, log expenses as you go, then close and capture the day’s net.') }}
                </flux:text>
            </div>
            @if ($this->activeShift)
                <flux:badge color="lime" class="shrink-0">
                    {{ __('Shift open') }}
                </flux:badge>
            @else
                <flux:badge color="zinc" class="shrink-0">
                    {{ __('No active shift') }}
                </flux:badge>
            @endif
        </div>
    </header>

    @include('partials.shifts-todays-doctor-payout', [
        'total' => $this->todaysDoctorPayoutTotal,
        'breakdown' => $this->todaysDoctorPayoutByDoctor,
        'showShareOutLink' => true,
    ])

    <flux:error name="shift" />

    @if ($this->activeShift)
        @php($s = $this->activeShift)
        <div class="grid gap-8 lg:grid-cols-5">
            <section class="lg:col-span-2 space-y-6">
                <flux:heading size="lg">{{ __('Shift status') }}</flux:heading>
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <dl class="space-y-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Opened by') }}</flux:text>
                            <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->opener?->name }}</flux:text>
                        </div>
                        <div class="flex justify-between gap-4">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Opened at') }}</flux:text>
                            <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $s->opened_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</flux:text>
                        </div>
                        <flux:separator />
                        <div class="flex justify-between gap-4">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Opening balance') }}</flux:text>
                            <flux:text class="font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ $this->formatMoney((int) $s->opening_balance) }}</flux:text>
                        </div>
                    </dl>
                </div>

                <form wire:submit="addExpense" class="space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-6 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:heading size="md">{{ __('Add expense') }}</flux:heading>
                    <flux:error name="expense" />
                    <flux:field>
                        <flux:label>{{ __('Label') }}</flux:label>
                        <flux:input wire:model="expenseLabel" placeholder="{{ __('e.g. Print roll, wires') }}" />
                        <flux:error name="expenseLabel" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Amount') }}</flux:label>
                        <flux:input type="number" wire:model="expenseAmount" min="1" step="1" placeholder="0" />
                        <flux:error name="expenseAmount" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="addExpense">{{ __('Add expense') }}</span>
                        <span wire:loading wire:target="addExpense">{{ __('Saving…') }}</span>
                    </flux:button>
                </form>
            </section>

            <section class="lg:col-span-3 space-y-6">
                <flux:heading size="lg">{{ __('Live summary') }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('OPD invoices (paid)') }}</flux:text>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney($s->totalPaidInvoicesForKind(InvoiceKind::Opd)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Lab invoices (paid)') }}</flux:text>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney($s->totalPaidInvoicesForKind(InvoiceKind::Lab)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Doctor shares (accrued)') }}</flux:text>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-amber-800 dark:text-amber-400">{{ $this->formatMoney($s->totalDoctorPayouts()) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Expenses') }}</flux:text>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->formatMoney($s->totalExpenses()) }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-5 sm:col-span-2 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-emerald-800 dark:text-emerald-300">{{ __('Net (preview)') }}</flux:text>
                        <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-900 dark:text-emerald-200">{{ $this->formatMoney($s->netAmount()) }}</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
                        <flux:heading size="md">{{ __('Expense log') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500">{{ $s->expenses->count() }} {{ __('entries') }}</flux:text>
                    </div>
                    @if ($s->expenses->isEmpty())
                        <div class="px-6 py-12 text-center">
                            <flux:text class="text-zinc-500">{{ __('No expenses yet this shift.') }}</flux:text>
                        </div>
                    @else
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($s->expenses as $expense)
                                <li wire:key="expense-{{ $expense->id }}" class="flex items-center justify-between gap-4 px-6 py-3">
                                    <flux:text class="font-medium text-zinc-800 dark:text-zinc-200">{{ $expense->label }}</flux:text>
                                    <flux:text class="tabular-nums text-zinc-600 dark:text-zinc-400">{{ $this->formatMoney((int) $expense->amount) }}</flux:text>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <flux:button
                        :href="route('reception.shift-close-summary', $s)"
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="outline"
                        icon="printer"
                    >
                        {{ __('Print summary') }}
                    </flux:button>
                    <flux:button variant="danger" icon="lock-closed" wire:click="confirmCloseModal" wire:loading.attr="disabled">
                        {{ __('Log & close shift') }}
                    </flux:button>
                </div>
            </section>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-2">
            <section class="rounded-2xl border border-zinc-200 bg-white p-8 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-2">{{ __('Open shift') }}</flux:heading>
                <flux:text class="mb-6 text-zinc-600 dark:text-zinc-400">
                    {{ __('Enter the cash float at hand. If this is the first shift today, queues for daily-reset services are closed automatically.') }}
                </flux:text>
                <form wire:submit="openShift" class="space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Opening balance') }}</flux:label>
                        <flux:input type="number" wire:model="openingBalance" min="0" step="1" />
                        <flux:error name="openingBalance" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" icon="sun" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="openShift">{{ __('Open shift') }}</span>
                        <span wire:loading wire:target="openShift">{{ __('Opening…') }}</span>
                    </flux:button>
                </form>
            </section>

            <section class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/30 p-8 dark:border-zinc-600 dark:bg-zinc-900/30">
                <flux:heading size="lg" class="mb-4">{{ __('Recent closed shifts') }}</flux:heading>
                @if ($this->recentClosedShifts->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('No history yet.') }}</flux:text>
                @else
                    <ul class="space-y-3">
                        @foreach ($this->recentClosedShifts as $closed)
                            <li wire:key="closed-{{ $closed->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200/80 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                                <div>
                                    <flux:text class="font-medium text-zinc-800 dark:text-zinc-200">{{ $closed->opener?->name }}</flux:text>
                                    <flux:text class="text-xs text-zinc-500">{{ $closed->closed_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</flux:text>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                    <flux:button
                                        :href="route('reception.shift-close-summary', $closed)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        size="sm"
                                        variant="outline"
                                        icon="printer"
                                    >
                                        {{ __('Print') }}
                                    </flux:button>
                                    <flux:badge color="zinc">{{ __('Closed') }}</flux:badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endif

    <flux:modal wire:model="showCloseModal" name="close-shift" class="min-w-[22rem] max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Close this shift?') }}</flux:heading>
            @if ($this->activeShift)
                @php($c = $this->activeShift)
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ __('This will finalize totals, stamp close time, and close token queues for per-shift services.') }}
                </flux:text>
                <ul class="space-y-2 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/50">
                    <li class="flex justify-between"><span class="text-zinc-500">{{ __('Opening') }}</span><span class="tabular-nums font-medium">{{ $this->formatMoney((int) $c->opening_balance) }}</span></li>
                    <li class="flex justify-between"><span class="text-zinc-500">{{ __('OPD invoices') }}</span><span class="tabular-nums font-medium">{{ $this->formatMoney($c->totalPaidInvoicesForKind(InvoiceKind::Opd)) }}</span></li>
                    <li class="flex justify-between"><span class="text-zinc-500">{{ __('Lab invoices') }}</span><span class="tabular-nums font-medium">{{ $this->formatMoney($c->totalPaidInvoicesForKind(InvoiceKind::Lab)) }}</span></li>
                    <li class="flex justify-between"><span class="text-zinc-500">{{ __('Doctor shares') }}</span><span class="tabular-nums font-medium">{{ $this->formatMoney($c->totalDoctorPayouts()) }}</span></li>
                    <li class="flex justify-between"><span class="text-zinc-500">{{ __('Expenses') }}</span><span class="tabular-nums font-medium">{{ $this->formatMoney($c->totalExpenses()) }}</span></li>
                    <flux:separator />
                    <li class="flex justify-between text-emerald-800 dark:text-emerald-300"><span class="font-semibold">{{ __('Net') }}</span><span class="tabular-nums font-bold">{{ $this->formatMoney($c->netAmount()) }}</span></li>
                </ul>
            @endif
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                @if ($this->activeShift)
                    <flux:button
                        :href="route('reception.shift-close-summary', $this->activeShift)"
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="outline"
                        icon="printer"
                        class="sm:me-auto"
                    >
                        {{ __('Print summary') }}
                    </flux:button>
                @endif
                <flux:button variant="ghost" wire:click="$set('showCloseModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="closeShift" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="closeShift">{{ __('Confirm close') }}</span>
                    <span wire:loading wire:target="closeShift">{{ __('Closing…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
