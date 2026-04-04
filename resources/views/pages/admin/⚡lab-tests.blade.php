<?php

use App\Enums\LabTestSourcing;
use App\Enums\UserRole;
use App\Models\InvoiceLabTest;
use App\Models\LabTest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lab tests')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $test_code = '';

    public string $sourcing = 'in_house';

    public int $days_required = 0;

    public int $price = 0;

    public int $hospital_share = 70;

    public int $lab_share = 30;

    public bool $is_active = true;

    public ?int $pendingDeleteId = null;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }
    }

    #[Computed]
    public function rows()
    {
        return LabTest::query()->withCount('invoiceLines')->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->test_code = '';
        $this->sourcing = LabTestSourcing::InHouse->value;
        $this->days_required = 0;
        $this->price = 0;
        $this->hospital_share = 70;
        $this->lab_share = 30;
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $t = LabTest::query()->findOrFail($id);
        $this->editingId = $t->id;
        $this->name = $t->name;
        $this->test_code = $t->test_code ?? '';
        $this->sourcing = $t->sourcing->value;
        $this->days_required = (int) $t->days_required;
        $this->price = (int) $t->price;
        $this->hospital_share = (int) $t->hospital_share;
        $this->lab_share = (int) $t->lab_share;
        $this->is_active = $t->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'test_code' => [
                'nullable',
                'string',
                'max:64',
                \Illuminate\Validation\Rule::unique('lab_tests', 'test_code')->ignore($this->editingId),
            ],
            'sourcing' => ['required', 'in:in_house,outsourced'],
            'days_required' => ['required', 'integer', 'min:0', 'max:365'],
            'price' => ['required', 'integer', 'min:0'],
            'hospital_share' => ['required', 'integer', 'min:0', 'max:100'],
            'lab_share' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => __('name'),
            'test_code' => __('Test code'),
        ]);

        if ($validated['hospital_share'] + $validated['lab_share'] !== 100) {
            $this->addError('hospital_share', __('Hospital share and lab share must add up to 100%.'));

            return;
        }

        $code = trim((string) ($validated['test_code'] ?? ''));
        $normalizedCode = $code === '' ? null : $code;

        $payload = [
            'name' => $validated['name'],
            'test_code' => $normalizedCode,
            'sourcing' => $validated['sourcing'],
            'days_required' => $validated['days_required'],
            'price' => $validated['price'],
            'hospital_share' => $validated['hospital_share'],
            'lab_share' => $validated['lab_share'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId) {
            LabTest::query()->whereKey($this->editingId)->update($payload);
        } else {
            LabTest::query()->create($payload);
        }

        $this->showModal = false;
        unset($this->rows);
    }

    public function confirmDelete(int $id): void
    {
        $this->pendingDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->pendingDeleteId) {
            return;
        }

        $test = LabTest::query()->find($this->pendingDeleteId);

        if (! $test) {
            $this->showDeleteModal = false;

            return;
        }

        if (InvoiceLabTest::query()->where('lab_test_id', $test->id)->exists()) {
            $this->addError('delete', __('Cannot delete: this test appears on past invoices. Deactivate it instead.'));
            $this->showDeleteModal = false;
            $this->pendingDeleteId = null;

            return;
        }

        try {
            $test->delete();
        } catch (QueryException) {
            $this->addError('delete', __('Cannot delete: this test is still referenced elsewhere.'));
            $this->showDeleteModal = false;
            $this->pendingDeleteId = null;

            return;
        }

        $this->showDeleteModal = false;
        $this->pendingDeleteId = null;
        unset($this->rows);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-cyan-50/50 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-cyan-950/25">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-cyan-400/15 blur-3xl dark:bg-cyan-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Lab tests') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Catalog tests, pricing, turnaround days, and hospital vs lab revenue split (percentages).') }}
                </flux:text>
            </div>
            <flux:button variant="primary" icon="plus" class="shrink-0" wire:click="openCreate">
                {{ __('Add lab test') }}
            </flux:button>
        </div>
    </header>

    <flux:error name="delete" />

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:heading size="md">{{ __('Catalog') }}</flux:heading>
            <flux:text class="mt-0.5 text-sm text-zinc-500">{{ __(':count configured', ['count' => $this->rows->count()]) }}</flux:text>
        </div>
        @if ($this->rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No lab tests yet. Create one for reception checkout.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Code') }}</th>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Source') }}</th>
                            <th class="px-6 py-3">{{ __('Days') }}</th>
                            <th class="px-6 py-3">{{ __('Price') }}</th>
                            <th class="px-6 py-3">{{ __('H / L %') }}</th>
                            <th class="px-6 py-3">{{ __('Uses') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rows as $row)
                            <tr wire:key="lab-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 font-mono text-sm text-zinc-700 dark:text-zinc-300">{{ $row->test_code ?: '—' }}</td>
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">{{ $row->name }}</td>
                                <td class="px-6 py-4">
                                    @if ($row->sourcing === \App\Enums\LabTestSourcing::InHouse)
                                        <flux:badge color="lime">{{ __('In house') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber">{{ __('Outsourced') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->days_required }}</td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ number_format($row->price) }}</td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->hospital_share }} / {{ $row->lab_share }}</td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->invoice_lines_count }}</td>
                                <td class="px-6 py-4">
                                    @if ($row->is_active)
                                        <flux:badge color="lime">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-end">
                                    <flux:button.group>
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEdit({{ $row->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600 hover:text-red-700 dark:text-red-400" wire:click="confirmDelete({{ $row->id }})">{{ __('Delete') }}</flux:button>
                                    </flux:button.group>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showModal" name="lab-test-form" class="min-w-[20rem] max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $this->editingId ? __('Edit lab test') : __('New lab test') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. CBC, Lipid profile') }}" />
                <flux:error name="name" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Test code') }}</flux:label>
                <flux:input wire:model="test_code" class="font-mono" placeholder="{{ __('Optional — e.g. CBC-01') }}" />
                <flux:error name="test_code" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Sourcing') }}</flux:label>
                <flux:select wire:model="sourcing">
                    <flux:select.option value="in_house">{{ __('In house') }}</flux:select.option>
                    <flux:select.option value="outsourced">{{ __('Outsourced') }}</flux:select.option>
                </flux:select>
                <flux:error name="sourcing" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Days required') }}</flux:label>
                <flux:input type="number" wire:model.number="days_required" min="0" max="365" />
                <flux:error name="days_required" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Price') }}</flux:label>
                <flux:input type="number" wire:model.number="price" min="0" />
                <flux:error name="price" />
            </flux:field>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Hospital share %') }}</flux:label>
                    <flux:input type="number" wire:model.number="hospital_share" min="0" max="100" />
                    <flux:error name="hospital_share" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Lab share %') }}</flux:label>
                    <flux:input type="number" wire:model.number="lab_share" min="0" max="100" />
                    <flux:error name="lab_share" />
                </flux:field>
            </div>
            <flux:field>
                <div class="flex items-center justify-between gap-4">
                    <flux:label>{{ __('Active') }}</flux:label>
                    <flux:switch wire:model="is_active" />
                </div>
            </flux:field>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" name="lab-test-delete" class="min-w-[20rem] max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete lab test?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('This cannot be undone. Tests used on invoices cannot be removed.') }}
            </flux:text>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
