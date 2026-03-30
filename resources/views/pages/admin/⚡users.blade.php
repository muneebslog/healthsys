<?php

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component
{
    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $editingName = '';

    public string $editingEmail = '';

    public string $role = '';

    public bool $is_active = true;

    public function mount(): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }
    }

    #[Computed]
    public function rows()
    {
        return User::query()
            ->when(filled($this->search), function ($q): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function roleOptions(): array
    {
        return [
            UserRole::Staff->value => __('Reception (staff)'),
            UserRole::Admin->value => __('Admin'),
            UserRole::Owner->value => __('Owner'),
            UserRole::Doctor->value => __('Doctor'),
            UserRole::FinanceManager->value => __('Finance manager'),
        ];
    }

    public function openEdit(int $id): void
    {
        $u = User::query()->findOrFail($id);
        $this->editingId = $u->id;
        $this->editingName = $u->name;
        $this->editingEmail = $u->email;
        $this->role = $u->role->value;
        $this->is_active = $u->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['boolean'],
        ]);

        $user = User::query()->findOrFail($this->editingId);
        $newRole = UserRole::from($validated['role']);

        $otherAdmins = User::query()
            ->where('role', UserRole::Admin)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($user->id === Auth::id()) {
            if ($newRole !== UserRole::Admin) {
                $this->addError('role', __('You cannot remove your own admin role.'));

                return;
            }
        }

        if ($user->role === UserRole::Admin && $newRole !== UserRole::Admin && ! $otherAdmins) {
            $this->addError('role', __('At least one admin account must remain.'));

            return;
        }

        if (! $validated['is_active'] && $user->role === UserRole::Admin && ! $otherAdmins) {
            $this->addError('is_active', __('Cannot deactivate the only admin account.'));

            return;
        }

        if ($user->role === UserRole::Doctor && $newRole !== UserRole::Doctor) {
            Doctor::query()->where('user_id', $user->id)->update(['user_id' => null]);
        }

        $user->update([
            'role' => $newRole,
            'is_active' => $validated['is_active'],
        ]);

        $this->showModal = false;
        unset($this->rows);
    }

    public function roleBadgeColor(UserRole $role): string
    {
        return match ($role) {
            UserRole::Admin => 'rose',
            UserRole::Staff => 'zinc',
            UserRole::Doctor => 'emerald',
            UserRole::Owner => 'amber',
            UserRole::FinanceManager => 'violet',
        };
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-rose-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-rose-950/20">
        <div class="pointer-events-none absolute -end-12 top-0 size-40 rounded-full bg-rose-400/15 blur-3xl dark:bg-rose-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Users & roles') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Assign roles and activation status. Changing a user from doctor clears their doctor profile login link.') }}
                </flux:text>
            </div>
        </div>
    </header>

    <flux:card class="overflow-hidden p-0">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:field>
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Name or email…') }}"
                />
            </flux:field>
        </div>
        @if ($this->rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No users match.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Email') }}</th>
                            <th class="px-6 py-3">{{ __('Role') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rows as $row)
                            <tr wire:key="user-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $row->name }}</span>
                                        @if ($row->id === auth()->id())
                                            <flux:badge size="sm" color="indigo">{{ __('You') }}</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row->email }}</td>
                                <td class="px-6 py-4">
                                    <flux:badge :color="$this->roleBadgeColor($row->role)">{{ $this->roleOptions()[$row->role->value] ?? $row->role->value }}</flux:badge>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row->is_active)
                                        <flux:badge color="lime">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-end">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEdit({{ $row->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>

    <flux:modal wire:model="showModal" name="user-role" class="min-w-[20rem] max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ __('Role & access') }}</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ $this->editingName }} — {{ $this->editingEmail }}
            </flux:text>
            <flux:field>
                <flux:label>{{ __('Role') }}</flux:label>
                @if ($this->editingId === auth()->id())
                    <flux:text class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        {{ $this->roleOptions()['admin'] }}
                        <span class="text-zinc-500">— {{ __('locked for your account') }}</span>
                    </flux:text>
                @else
                    <flux:select wire:model="role">
                        @foreach ($this->roleOptions() as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:error name="role" />
            </flux:field>
            <flux:field>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:label>{{ __('Account active') }}</flux:label>
                        <flux:text class="text-sm text-zinc-500">{{ __('Inactive users cannot sign in.') }}</flux:text>
                    </div>
                    <flux:switch wire:model="is_active" />
                </div>
                <flux:error name="is_active" />
            </flux:field>
            @if ($this->editingId === auth()->id())
                <flux:callout icon="information-circle" color="amber">
                    {{ __('Your role stays admin. If you are the only admin, you cannot deactivate this account.') }}
                </flux:callout>
            @endif
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
