<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service prices')] class extends Component
{
    public ?int $filterServiceId = null;

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $svcId = null;

    public ?int $docId = null;

    public string $price = '';

    public string $doctor_share = '0';

    public string $hospital_share = '100';

    public bool $is_active = true;

    public ?string $editingDoctorName = null;

    public ?int $pendingDeleteId = null;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }
    }

    #[Computed]
    public function serviceOptions()
    {
        return Service::query()->orderBy('name')->get();
    }

    #[Computed]
    public function rows()
    {
        return ServicePrice::query()
            ->with(['service', 'doctor'])
            ->when($this->filterServiceId, fn ($q) => $q->where('service_id', $this->filterServiceId))
            ->join('services', 'services.id', '=', 'service_prices.service_id')
            ->orderBy('services.name')
            ->orderBy('service_prices.doctor_id')
            ->select('service_prices.*')
            ->get();
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        return $this->svcId ? Service::query()->find($this->svcId) : null;
    }

    /**
     * Active doctors that do not yet have a price row for the selected service (used when creating a row).
     *
     * @return \Illuminate\Support\Collection<int, Doctor>
     */
    #[Computed]
    public function doctorsForNewPrice()
    {
        if (! $this->svcId || $this->selectedService?->is_standalone) {
            return collect();
        }

        $assignedIds = ServicePrice::query()
            ->where('service_id', $this->svcId)
            ->whereNotNull('doctor_id')
            ->pluck('doctor_id');

        return Doctor::query()
            ->where('status', 'active')
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }

    public function updatedSvcId(mixed $value): void
    {
        if ($value === '' || $value === null) {
            $this->svcId = null;
            unset($this->doctorsForNewPrice, $this->selectedService);

            return;
        }

        $service = $this->svcId ? Service::query()->find($this->svcId) : null;

        if ($service?->is_standalone) {
            $this->docId = null;
            $this->doctor_share = '0';
            $this->hospital_share = '100';
        }

        unset($this->doctorsForNewPrice);
        unset($this->selectedService);
    }

    public function updatedDocId(mixed $value): void
    {
        if ($value === '' || $value === null) {
            $this->docId = null;
        }
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->svcId = $this->filterServiceId ?: null;
        $this->docId = null;
        $this->price = '';
        $this->doctor_share = '0';
        $this->hospital_share = '100';
        $this->is_active = true;
        $this->editingDoctorName = null;
        $this->resetErrorBag();
        $this->showModal = true;
        unset($this->selectedService);
        unset($this->doctorsForNewPrice);
    }

    public function openEdit(int $id): void
    {
        $row = ServicePrice::query()->with('service')->findOrFail($id);
        $this->editingId = $row->id;
        $this->svcId = $row->service_id;
        $this->docId = $row->doctor_id;
        $this->price = (string) $row->price;
        $this->doctor_share = (string) $row->doctor_share;
        $this->hospital_share = (string) $row->hospital_share;
        $this->is_active = $row->is_active;
        $this->editingDoctorName = $row->doctor?->name;
        $this->resetErrorBag();
        $this->showModal = true;
        unset($this->selectedService);
        unset($this->doctorsForNewPrice);
    }

    public function save(): void
    {
        $this->validate([
            'svcId' => ['required', 'exists:services,id'],
            'price' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ], [], [
            'svcId' => __('service'),
            'price' => __('price'),
        ]);

        $service = Service::query()->findOrFail($this->svcId);

        if ($service->is_standalone) {
            $docId = null;
            $doctorShare = 0;
            $hospitalShare = 100;
        } else {
            $this->validate([
                'docId' => ['required', 'exists:doctors,id'],
                'doctor_share' => ['required', 'integer', 'min:0', 'max:100'],
                'hospital_share' => ['required', 'integer', 'min:0', 'max:100'],
            ], [], [
                'docId' => __('doctor'),
            ]);

            $doctorShare = (int) $this->doctor_share;
            $hospitalShare = (int) $this->hospital_share;

            if ($doctorShare + $hospitalShare !== 100) {
                $this->addError('doctor_share', __('Doctor share and hospital share must total 100%.'));

                return;
            }

            $docId = $this->docId;
        }

        $exists = ServicePrice::query()
            ->where('service_id', $this->svcId)
            ->when(
                $docId === null,
                fn ($q) => $q->whereNull('doctor_id'),
                fn ($q) => $q->where('doctor_id', $docId)
            )
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($exists) {
            $this->addError('svcId', __('A price row already exists for this service and doctor combination.'));

            return;
        }

        $payload = [
            'service_id' => $this->svcId,
            'doctor_id' => $docId,
            'price' => (int) $this->price,
            'doctor_share' => $doctorShare,
            'hospital_share' => $hospitalShare,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            ServicePrice::query()->whereKey($this->editingId)->update($payload);
        } else {
            ServicePrice::query()->create($payload);
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

        try {
            ServicePrice::query()->whereKey($this->pendingDeleteId)->delete();
        } catch (QueryException) {
            $this->addError('delete', __('Cannot delete: this price row is referenced by past invoices or visits.'));

            $this->showDeleteModal = false;
            $this->pendingDeleteId = null;

            return;
        }

        $this->showDeleteModal = false;
        $this->pendingDeleteId = null;
        unset($this->rows);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-fuchsia-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-fuchsia-950/20">
        <div class="pointer-events-none absolute -bottom-20 end-0 size-56 rounded-full bg-fuchsia-400/15 blur-3xl dark:bg-fuchsia-500/10"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Service prices') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Set price and revenue split per service. Standalone services use a single row without a doctor; doctor services need one row per doctor.') }}
                </flux:text>
            </div>
            <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-end">
                <flux:field class="min-w-[12rem]">
                    <flux:label>{{ __('Filter by service') }}</flux:label>
                    <flux:select wire:model.live="filterServiceId" placeholder="{{ __('All services') }}">
                        <flux:select.option value="">{{ __('All services') }}</flux:select.option>
                        @foreach ($this->serviceOptions as $opt)
                            <flux:select.option value="{{ $opt->id }}">{{ $opt->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:button variant="primary" icon="plus" wire:click="openCreate">
                    {{ __('Add price') }}
                </flux:button>
            </div>
        </div>
    </header>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:heading size="md">{{ __('Price matrix') }}</flux:heading>
            <flux:text class="mt-0.5 text-sm text-zinc-500">{{ __(':count rows', ['count' => $this->rows->count()]) }}</flux:text>
        </div>
        @if ($this->rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No prices yet. Add a row for each standalone service or doctor pairing.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Service') }}</th>
                            <th class="px-6 py-3">{{ __('Doctor') }}</th>
                            <th class="px-6 py-3">{{ __('Price') }}</th>
                            <th class="px-6 py-3">{{ __('Doc %') }}</th>
                            <th class="px-6 py-3">{{ __('Hospital %') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rows as $row)
                            <tr wire:key="sp-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">{{ $row->service->name }}</td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $row->doctor ? $row->doctor->name : __('Standalone') }}
                                </td>
                                <td class="px-6 py-4 tabular-nums text-zinc-800 dark:text-zinc-200">{{ number_format((int) $row->price) }}</td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->doctor_share }}%</td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->hospital_share }}%</td>
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

    <flux:error name="delete" />

    <flux:callout variant="info" icon="information-circle" class="max-w-3xl">
        {{ __('Walk-in and queues only offer doctors who have an active price row for that service. Add the row here first.') }}
    </flux:callout>

    <flux:modal wire:model="showModal" name="price-form" class="min-w-[22rem] max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $this->editingId ? __('Edit price') : __('New price') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Service') }}</flux:label>
                <flux:select wire:model.live="svcId" placeholder="{{ __('Choose service') }}">
                    <flux:select.option value="">{{ __('Choose service') }}</flux:select.option>
                    @foreach ($this->serviceOptions as $opt)
                        <flux:select.option value="{{ $opt->id }}">{{ $opt->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="svcId" />
            </flux:field>

            @if ($this->selectedService && ! $this->selectedService->is_standalone)
                <flux:field>
                    <flux:label>{{ __('Doctor') }}</flux:label>
                    @if ($this->editingId)
                        <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $this->editingDoctorName ?? '—' }}</flux:text>
                        <flux:text class="text-sm text-zinc-500">{{ __('Doctor cannot be changed; delete and recreate if needed.') }}</flux:text>
                    @else
                        <flux:select wire:model.live="docId" placeholder="{{ __('Choose doctor') }}">
                            <flux:select.option value="">{{ __('Choose doctor') }}</flux:select.option>
                            @foreach ($this->doctorsForNewPrice as $d)
                                <flux:select.option value="{{ $d->id }}">{{ $d->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if ($this->svcId && $this->doctorsForNewPrice->isEmpty())
                            <flux:text class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                                {{ __('Every active doctor already has a price row for this service, or add more doctors under Doctors.') }}
                            </flux:text>
                        @endif
                    @endif
                    <flux:error name="docId" />
                </flux:field>
            @elseif ($this->selectedService?->is_standalone)
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Standalone: price applies without a doctor; shares default to 0% / 100%.') }}
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>{{ __('Price') }}</flux:label>
                <flux:input type="number" wire:model="price" min="1" step="1" placeholder="0" />
                <flux:error name="price" />
            </flux:field>

            @if ($this->selectedService && ! $this->selectedService->is_standalone)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Doctor share %') }}</flux:label>
                        <flux:input type="number" wire:model.live="doctor_share" min="0" max="100" step="1" />
                        <flux:error name="doctor_share" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Hospital share %') }}</flux:label>
                        <flux:input type="number" wire:model.live="hospital_share" min="0" max="100" step="1" />
                        <flux:error name="hospital_share" />
                    </flux:field>
                </div>
            @endif

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

    <flux:modal wire:model="showDeleteModal" name="price-delete" class="min-w-[20rem] max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete price row?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('Removes this service–doctor price configuration. Past invoices are unchanged.') }}
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
