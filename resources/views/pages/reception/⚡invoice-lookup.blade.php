<?php

use App\Enums\InvoiceKind;
use App\Enums\UserRole;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invoice lookup')] class extends Component
{
    public string $invoiceNumber = '';

    public ?int $loadedInvoiceId = null;

    public function mount(): void
    {
        if (config('hms.skip_role_page_guards')) {
            return;
        }

        $role = Auth::user()?->role;

        if (! in_array($role, [UserRole::Staff, UserRole::Admin, UserRole::Owner, UserRole::FinanceManager], true)) {
            abort(403);
        }
    }

    public function lookup(): void
    {
        $this->validate([
            'invoiceNumber' => ['required', 'integer', 'min:1', Rule::exists('invoices', 'id')],
        ], [
            'invoiceNumber.exists' => __('No invoice exists with this number.'),
        ], [
            'invoiceNumber' => __('Invoice number'),
        ]);

        $this->loadedInvoiceId = (int) $this->invoiceNumber;
    }

    public function clearResult(): void
    {
        $this->loadedInvoiceId = null;
        $this->invoiceNumber = '';
        $this->resetErrorBag();
    }

    #[Computed]
    public function invoiceRecord(): ?Invoice
    {
        if ($this->loadedInvoiceId === null) {
            return null;
        }

        return Invoice::query()
            ->whereKey($this->loadedInvoiceId)
            ->with([
                'patient.family',
                'shift',
                'procedure.doctor',
                'visit.doctor',
                'visit.services' => fn ($q) => $q->orderBy('id')->with([
                    'service',
                    'doctor',
                    'queueToken.queue.service',
                    'queueToken.queue.doctor',
                    'queueToken.queue.shift',
                ]),
                'services' => fn ($q) => $q->orderBy('id')->with(['service', 'doctor']),
                'labTests' => fn ($q) => $q->orderBy('id'),
            ])
            ->first();
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }

    public function formatDt(?CarbonInterface $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $value->timezone(config('app.timezone'))->format('M j, Y g:i A');
    }

    /**
     * @return list<array{prefix: string, invoice_service: \App\Models\InvoiceService, visit_service: ?\App\Models\VisitService}>
     */
    protected function opdLinePairs(Invoice $invoice): array
    {
        $lines = $invoice->services->sortBy('id')->values();
        $visitServices = $invoice->visit?->services ?? collect();

        $out = [];
        foreach ($lines as $idx => $is) {
            $prefix = $idx < 26 ? chr(65 + $idx) : 'S'.($idx + 1);
            $out[] = [
                'prefix' => $prefix,
                'invoice_service' => $is,
                'visit_service' => $visitServices->get($idx),
            ];
        }

        return $out;
    }
}; ?>

@php($inv = $this->invoiceRecord)
<div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" class="text-zinc-900 dark:text-white">{{ __('Invoice lookup') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500">
            {{ __('Enter the invoice ID printed on the slip to see patient, lines, tokens, and queues.') }}
        </flux:text>
    </div>

    <flux:card class="p-6 sm:p-8">
        <form wire:submit="lookup" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <flux:field class="min-w-0 flex-1">
                <flux:label>{{ __('Invoice number') }}</flux:label>
                <flux:input
                    wire:model="invoiceNumber"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    step="1"
                    placeholder="{{ __('e.g. 42') }}"
                    icon="hashtag"
                />
                <flux:error name="invoiceNumber" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled">
                {{ __('Look up') }}
            </flux:button>
        </form>
    </flux:card>

    @if ($inv)
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge color="zinc">#{{ $inv->id }}</flux:badge>
            <flux:badge color="cyan">{{ Str::headline($inv->kind->value) }}</flux:badge>
            <flux:badge color="zinc">{{ Str::headline($inv->status->value) }}</flux:badge>
            <flux:button size="sm" variant="ghost" wire:click="clearResult">{{ __('Clear') }}</flux:button>
            @if (config('hms.skip_role_page_guards') || in_array(auth()->user()->role, [\App\Enums\UserRole::Staff, \App\Enums\UserRole::Admin], true))
                <flux:button size="sm" variant="outline" icon="printer" tag="a" :href="route('invoices.print', $inv)" target="_blank" rel="noopener noreferrer">
                    {{ __('Print') }}
                </flux:button>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="p-6">
                <flux:heading size="md" class="mb-4">{{ __('Patient') }}</flux:heading>
                @if ($inv->patient)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Name') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ $inv->patient->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Type') }}</dt>
                            <dd class="text-zinc-800 dark:text-zinc-200">{{ Str::headline($inv->patient->type->value) }}</dd>
                        </div>
                        @if ($inv->patient->family?->phone)
                            <div class="flex justify-between gap-4">
                                <dt class="text-zinc-500">{{ __('Family phone') }}</dt>
                                <dd class="tabular-nums text-zinc-800 dark:text-zinc-200">{{ $inv->patient->family->phone }}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <flux:text class="text-zinc-500">{{ __('No patient linked.') }}</flux:text>
                @endif
            </flux:card>

            <flux:card class="p-6">
                <flux:heading size="md" class="mb-4">{{ __('Invoice summary') }}</flux:heading>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Created') }}</dt>
                        <dd class="text-end text-zinc-800 dark:text-zinc-200">{{ $this->formatDt($inv->created_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Last updated') }}</dt>
                        <dd class="text-end text-zinc-800 dark:text-zinc-200">{{ $this->formatDt($inv->updated_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Shift') }}</dt>
                        <dd class="tabular-nums text-zinc-800 dark:text-zinc-200">#{{ $inv->shift_id }}</dd>
                    </div>
                    @if ($inv->visit_id)
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Visit') }}</dt>
                            <dd class="tabular-nums text-zinc-800 dark:text-zinc-200">#{{ $inv->visit_id }}</dd>
                        </div>
                    @endif
                    @if ($inv->kind === InvoiceKind::Procedure && $inv->procedure)
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Procedure') }}</dt>
                            <dd class="text-end text-zinc-800 dark:text-zinc-200">{{ $inv->procedure->reference_number }} — {{ $inv->procedure->operation_name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Total') }}</dt>
                        <dd class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ $this->formatMoney((int) $inv->total_amount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Discount') }}</dt>
                        <dd class="tabular-nums text-zinc-800 dark:text-zinc-200">{{ $this->formatMoney((int) $inv->discount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                        <dt class="text-zinc-500">{{ __('Final') }}</dt>
                        <dd class="tabular-nums font-semibold text-teal-700 dark:text-teal-300">{{ $this->formatMoney((int) $inv->final_amount) }}</dd>
                    </div>
                    @if (filled($inv->payment_note))
                        <div class="border-t border-zinc-100 pt-2 dark:border-zinc-800">
                            <dt class="text-zinc-500">{{ __('Payment note') }}</dt>
                            <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $inv->payment_note }}</dd>
                        </div>
                    @endif
                </dl>
            </flux:card>
        </div>

        @if ($inv->kind === InvoiceKind::Lab && $inv->labTests->isNotEmpty())
            <flux:card class="overflow-x-auto p-6">
                <flux:heading size="md" class="mb-4">{{ __('Lab tests') }}</flux:heading>
                <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Lab invoices do not use queue tokens on the printed slip.') }}</flux:text>
                <table class="w-full min-w-[36rem] text-start text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ __('Test') }}</th>
                            <th class="px-3 py-2 font-medium text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($inv->labTests as $lt)
                            <tr wire:key="lab-line-{{ $lt->id }}">
                                <td class="px-3 py-2 text-zinc-900 dark:text-white">
                                    {{ filled($lt->test_code) ? $lt->test_code.' — ' : '' }}{{ $lt->test_name }}
                                </td>
                                <td class="px-3 py-2 text-end tabular-nums text-zinc-800 dark:text-zinc-200">{{ $this->formatMoney((int) $lt->line_final_amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </flux:card>
        @endif

        @if ($inv->kind === InvoiceKind::Procedure && $inv->services->isEmpty())
            <flux:callout icon="information-circle" color="zinc">
                {{ __('Procedure payments are stored on this invoice without per-line services; there are no queue tokens for this type.') }}
            </flux:callout>
        @endif

        @if ($inv->services->isNotEmpty())
            <flux:card class="overflow-x-auto p-6">
                <flux:heading size="md" class="mb-4">{{ __('Services, tokens & queues') }}</flux:heading>
                <flux:text class="mb-4 text-sm text-zinc-500">
                    {{ __('Token and queue are taken from the visit line that matches each invoice row (same order as the printed slip).') }}
                </flux:text>
                <table class="w-full min-w-[48rem] text-start text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ __('Row') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Service') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Doctor') }}</th>
                            <th class="px-3 py-2 font-medium text-end">{{ __('Final') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Token') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Queue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->opdLinePairs($inv) as $pair)
                            @php($is = $pair['invoice_service'])
                            @php($vs = $pair['visit_service'])
                            @php($tok = $vs?->queueToken)
                            @php($q = $tok?->queue)
                            <tr wire:key="inv-svc-{{ $is->id }}">
                                <td class="px-3 py-2 font-mono text-zinc-600 dark:text-zinc-400">{{ $pair['prefix'] }}</td>
                                <td class="px-3 py-2 text-zinc-900 dark:text-white">{{ $is->service?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $is->doctor?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-end tabular-nums font-medium text-zinc-900 dark:text-white">{{ $this->formatMoney((int) $is->final_amount) }}</td>
                                <td class="px-3 py-2 tabular-nums text-zinc-800 dark:text-zinc-200">
                                    @if ($tok)
                                        @if ($inv->services->count() > 1)
                                            {{ $pair['prefix'] }}·{{ $tok->token_number }}
                                        @else
                                            {{ $tok->token_number }}
                                        @endif
                                        <span class="ms-1 text-xs text-zinc-500">({{ Str::headline($tok->status->value) }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">
                                    @if ($q)
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $q->service?->name ?? '—' }}</div>
                                        <div class="text-xs text-zinc-500">
                                            {{ $q->doctor ? $q->doctor->name : __('Standalone') }}
                                            · {{ __('Queue') }} #{{ $q->id }}
                                            · {{ __('Shift') }} #{{ $q->shift_id }}
                                            · {{ Str::headline($q->status->value) }}
                                        </div>
                                        <flux:link :href="route('queues.control', $q)" class="text-xs" wire:navigate>{{ __('Open queue control') }}</flux:link>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </flux:card>
        @endif
    @endif
</div>
