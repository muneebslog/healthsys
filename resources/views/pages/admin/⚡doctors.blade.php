<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Doctors')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $specialization = '';

    public string $phone = '';

    /** Local time for `<input type="time">` (HH:MM) or empty when not set */
    public string $start_time = '';

    public string $end_time = '';

    public string $status = 'active';

    public bool $is_on_payroll = false;

    public ?int $user_id = null;

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
        return Doctor::query()->with('user')->orderBy('name')->get();
    }

    #[Computed]
    public function eligibleUsers()
    {
        return User::query()
            ->where('role', UserRole::Doctor)
            ->where(function ($q): void {
                $q->whereDoesntHave('doctor');
                if ($this->editingId) {
                    $q->orWhereHas('doctor', fn ($d) => $d->whereKey($this->editingId));
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->specialization = '';
        $this->phone = '';
        $this->start_time = '';
        $this->end_time = '';
        $this->status = 'active';
        $this->is_on_payroll = false;
        $this->user_id = null;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $d = Doctor::query()->findOrFail($id);
        $this->editingId = $d->id;
        $this->name = $d->name;
        $this->specialization = (string) ($d->specialization ?? '');
        $this->phone = (string) ($d->phone ?? '');
        $this->start_time = $this->timeInputValue($d->start_time);
        $this->end_time = $this->timeInputValue($d->end_time);
        $this->status = $d->status;
        $this->is_on_payroll = $d->is_on_payroll;
        $this->user_id = $d->user_id;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        if (! filled($this->user_id)) {
            $this->user_id = null;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'start_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'end_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'status' => ['required', 'in:active,inactive'],
            'is_on_payroll' => ['boolean'],
            'user_id' => ['nullable', 'exists:users,id'],
        ], [], [
            'name' => __('name'),
        ]);

        $startEmpty = $validated['start_time'] === '' || $validated['start_time'] === null;
        $endEmpty = $validated['end_time'] === '' || $validated['end_time'] === null;
        if ($startEmpty xor $endEmpty) {
            $this->addError('start_time', __('Set both start and end time, or clear both for no appointment grid.'));

            return;
        }
        if (! $startEmpty && ! $endEmpty) {
            $s = Carbon::parse('2000-01-01 '.$validated['start_time'].':00');
            $e = Carbon::parse('2000-01-01 '.$validated['end_time'].':00');
            if ($e->lte($s)) {
                $this->addError('end_time', __('End time must be after start time.'));

                return;
            }
        }

        if ($validated['user_id'] ?? null) {
            $user = User::query()->find($validated['user_id']);
            if (! $user || $user->role !== UserRole::Doctor) {
                $this->addError('user_id', __('Only users with the doctor role can be linked.'));

                return;
            }
            $taken = Doctor::query()
                ->where('user_id', $validated['user_id'])
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->exists();
            if ($taken) {
                $this->addError('user_id', __('That account is already linked to another doctor.'));

                return;
            }
        }

        $payload = [
            'name' => $validated['name'],
            'specialization' => $validated['specialization'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'start_time' => $startEmpty ? null : ($validated['start_time'].':00'),
            'end_time' => $endEmpty ? null : ($validated['end_time'].':00'),
            'status' => $validated['status'],
            'is_on_payroll' => $validated['is_on_payroll'],
            'user_id' => $validated['user_id'],
        ];

        if ($this->editingId) {
            Doctor::query()->whereKey($this->editingId)->update($payload);
        } else {
            Doctor::query()->create($payload);
        }

        $this->showModal = false;
        unset($this->rows);
        unset($this->eligibleUsers);
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

        $doctor = Doctor::query()->find($this->pendingDeleteId);

        if (! $doctor) {
            $this->showDeleteModal = false;

            return;
        }

        try {
            $doctor->delete();
        } catch (QueryException) {
            $this->addError('delete', __('Cannot delete: this doctor is referenced by visits, queues, or pricing. Set status to inactive instead.'));
            $this->showDeleteModal = false;
            $this->pendingDeleteId = null;

            return;
        }

        $this->showDeleteModal = false;
        $this->pendingDeleteId = null;
        unset($this->rows);
        unset($this->eligibleUsers);
    }

    /**
     * Format DB time for HTML time input (HH:MM).
     */
    protected function timeInputValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('H:i');
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-indigo-50/45 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-indigo-950/25">
        <div class="pointer-events-none absolute -start-16 top-1/2 size-48 -translate-y-1/2 rounded-full bg-indigo-400/15 blur-3xl dark:bg-indigo-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Doctors') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Maintain doctor profiles, reception hours (appointments grid), payroll flags, and optional login links for the doctor portal.') }}
                </flux:text>
            </div>
            <flux:button variant="primary" icon="plus" class="shrink-0" wire:click="openCreate">
                {{ __('Add doctor') }}
            </flux:button>
        </div>
    </header>

    <flux:error name="delete" />

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:heading size="md">{{ __('Directory') }}</flux:heading>
            <flux:text class="mt-0.5 text-sm text-zinc-500">{{ __(':count doctors', ['count' => $this->rows->count()]) }}</flux:text>
        </div>
        @if ($this->rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No doctors yet. Add profiles before assigning service prices.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Specialization') }}</th>
                            <th class="px-6 py-3">{{ __('Phone') }}</th>
                            <th class="px-6 py-3">{{ __('Reception hours') }}</th>
                            <th class="px-6 py-3">{{ __('Payroll') }}</th>
                            <th class="px-6 py-3">{{ __('Login') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rows as $row)
                            <tr wire:key="doc-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">{{ $row->name }}</td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-400">{{ $row->specialization ?: '—' }}</td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->phone ?: '—' }}</td>
                                <td class="px-6 py-4 tabular-nums text-sm text-zinc-600 dark:text-zinc-400">
                                    @if ($row->hasWorkingHours())
                                        {{ \Carbon\Carbon::parse($row->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($row->end_time)->format('H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row->is_on_payroll)
                                        <flux:badge color="indigo">{{ __('Yes') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('No') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $row->user?->email ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row->status === 'active')
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

    <flux:modal wire:model="showModal" name="doctor-form" class="min-w-[20rem] max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $this->editingId ? __('Edit doctor') : __('New doctor') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('Full name') }}" />
                <flux:error name="name" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Specialization') }}</flux:label>
                <flux:input wire:model="specialization" placeholder="{{ __('e.g. General practice') }}" />
                <flux:error name="specialization" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Phone') }}</flux:label>
                <flux:input wire:model="phone" type="tel" placeholder="{{ __('Optional') }}" />
                <flux:error name="phone" />
            </flux:field>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Reception start') }}</flux:label>
                    <flux:text class="mb-1 text-sm text-zinc-500">{{ __('Used for the appointments grid (5-minute slots). Leave both empty to hide this doctor there.') }}</flux:text>
                    <flux:input wire:model="start_time" type="time" step="300" />
                    <flux:error name="start_time" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Reception end') }}</flux:label>
                    <flux:input wire:model="end_time" type="time" step="300" />
                    <flux:error name="end_time" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model="status">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
                <flux:error name="status" />
            </flux:field>
            <flux:field>
                <div class="flex items-center justify-between gap-4">
                    <flux:label>{{ __('On payroll') }}</flux:label>
                    <flux:switch wire:model="is_on_payroll" />
                </div>
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Linked user (optional)') }}</flux:label>
                <flux:text class="mb-2 text-sm text-zinc-500">{{ __('Doctor-role account for portal access.') }}</flux:text>
                <flux:select wire:model.live="user_id" placeholder="{{ __('No login link') }}">
                    <flux:select.option value="">{{ __('No login link') }}</flux:select.option>
                    @foreach ($this->eligibleUsers as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="user_id" />
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

    <flux:modal wire:model="showDeleteModal" name="doctor-delete" class="min-w-[20rem] max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete doctor?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('Removes the profile if nothing references it. Otherwise use inactive status.') }}
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
