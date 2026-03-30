<?php

use App\Enums\QueueResetType;
use App\Enums\UserRole;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Services')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_standalone = false;

    public string $reset_type = 'daily';

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
        return Service::query()->withCount('prices')->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->is_standalone = false;
        $this->reset_type = QueueResetType::Daily->value;
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $s = Service::query()->findOrFail($id);
        $this->editingId = $s->id;
        $this->name = $s->name;
        $this->is_standalone = $s->is_standalone;
        $this->reset_type = $s->reset_type->value;
        $this->is_active = $s->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_standalone' => ['boolean'],
            'reset_type' => ['required', 'in:per_shift,daily'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => __('name'),
        ]);

        $payload = [
            'name' => $validated['name'],
            'is_standalone' => $validated['is_standalone'],
            'reset_type' => $validated['reset_type'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId) {
            Service::query()->whereKey($this->editingId)->update($payload);
        } else {
            Service::query()->create($payload);
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

        $service = Service::query()->find($this->pendingDeleteId);

        if (! $service) {
            $this->showDeleteModal = false;

            return;
        }

        if (Queue::query()->where('service_id', $service->id)->exists()) {
            $this->addError('delete', __('Cannot delete: this service has queue history. Deactivate it instead.'));
            $this->showDeleteModal = false;
            $this->pendingDeleteId = null;

            return;
        }

        try {
            $service->delete();
        } catch (QueryException) {
            $this->addError('delete', __('Cannot delete: this service is still referenced elsewhere.'));
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
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-violet-50/50 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/25">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-violet-400/15 blur-3xl dark:bg-violet-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Services') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Define clinic services, token reset behaviour, and whether a doctor is required on the walk-in flow.') }}
                </flux:text>
            </div>
            <flux:button variant="primary" icon="plus" class="shrink-0" wire:click="openCreate">
                {{ __('Add service') }}
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
                <flux:text class="text-zinc-500">{{ __('No services yet. Create one to power queues and pricing.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Mode') }}</th>
                            <th class="px-6 py-3">{{ __('Queue reset') }}</th>
                            <th class="px-6 py-3">{{ __('Prices') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rows as $row)
                            <tr wire:key="svc-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">{{ $row->name }}</td>
                                <td class="px-6 py-4">
                                    @if ($row->is_standalone)
                                        <flux:badge color="zinc">{{ __('Standalone') }}</flux:badge>
                                    @else
                                        <flux:badge color="violet">{{ __('With doctor') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $row->reset_type === \App\Enums\QueueResetType::PerShift ? __('Per shift') : __('Daily') }}
                                </td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->prices_count }}</td>
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

    <flux:modal wire:model="showModal" name="service-form" class="min-w-[20rem] max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $this->editingId ? __('Edit service') : __('New service') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. Consultation, IV drip') }}" />
                <flux:error name="name" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Token queue reset') }}</flux:label>
                <flux:select wire:model="reset_type">
                    <flux:select.option value="per_shift">{{ __('Per shift — reset when shift closes') }}</flux:select.option>
                    <flux:select.option value="daily">{{ __('Daily — reset on first shift of a new day') }}</flux:select.option>
                </flux:select>
                <flux:error name="reset_type" />
            </flux:field>
            <flux:field>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:label>{{ __('Standalone service') }}</flux:label>
                        <flux:text class="text-sm text-zinc-500">{{ __('No doctor selection on walk-in; one price row without doctor.') }}</flux:text>
                    </div>
                    <flux:switch wire:model="is_standalone" />
                </div>
            </flux:field>
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

    <flux:modal wire:model="showDeleteModal" name="service-delete" class="min-w-[20rem] max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete service?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('This cannot be undone. Services linked to queues cannot be removed.') }}
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
