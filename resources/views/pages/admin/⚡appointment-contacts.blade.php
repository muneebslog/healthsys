<?php

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Consultation contacts')] class extends Component
{
    /** Matches reception appointments: Consultation service row id. */
    private const int CONSULTATION_SERVICE_ID = 1;

    /** Empty string when no doctor selected (matches Flux select option values). */
    public string $doctorId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        if (! config('hms.skip_role_page_guards') && Auth::user()->role !== UserRole::Admin) {
            abort(403);
        }

        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->addDays(60)->toDateString();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Doctor>
     */
    #[Computed]
    public function doctors()
    {
        return Doctor::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Appointment>
     */
    #[Computed]
    public function rows()
    {
        if ($this->doctorId === '' || $this->doctorId === '0') {
            return Appointment::query()->whereRaw('1 = 0')->get();
        }

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return Appointment::query()
            ->where('service_id', self::CONSULTATION_SERVICE_ID)
            ->where('doctor_id', (int) $this->doctorId)
            ->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::Arrived])
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->with(['patient', 'family', 'doctor'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();
    }

    protected function dialDigits(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-emerald-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950/20">
        <div class="pointer-events-none absolute -end-20 -top-24 size-56 rounded-full bg-emerald-400/15 blur-3xl dark:bg-emerald-500/10"></div>
        <div class="relative">
            <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                {{ __('Consultation contacts') }}
            </flux:heading>
            <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                {{ __('Family phone numbers for consultation appointments—call to confirm, reschedule, or follow up. Admin only.') }}
            </flux:text>
        </div>
    </header>

    <div class="grid gap-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900 sm:grid-cols-2 lg:grid-cols-4">
        <flux:field class="sm:col-span-2">
            <flux:label>{{ __('Doctor') }}</flux:label>
            <flux:select wire:model.live="doctorId" placeholder="{{ __('Choose a doctor') }}">
                <flux:select.option value="">{{ __('Choose a doctor') }}</flux:select.option>
                @foreach ($this->doctors as $d)
                    <flux:select.option value="{{ $d->id }}" wire:key="doc-opt-{{ $d->id }}">{{ $d->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('From') }}</flux:label>
            <flux:input type="date" wire:model.live="dateFrom" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('To') }}</flux:label>
            <flux:input type="date" wire:model.live="dateTo" />
        </flux:field>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:heading size="md">{{ __('Appointments') }}</flux:heading>
            @if ($this->doctorId === '' || $this->doctorId === '0')
                <flux:text class="mt-0.5 text-sm text-zinc-500">{{ __('Select a doctor to load contact numbers.') }}</flux:text>
            @else
                <flux:text class="mt-0.5 text-sm text-zinc-500">{{ __(':count in range', ['count' => $this->rows->count()]) }}</flux:text>
            @endif
        </div>

        @if (($this->doctorId !== '' && $this->doctorId !== '0') && $this->rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <flux:text class="text-zinc-500">{{ __('No booked or arrived consultation appointments in this date range.') }}</flux:text>
            </div>
        @elseif ($this->doctorId !== '' && $this->doctorId !== '0')
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-zinc-100 bg-zinc-50/80 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3">{{ __('Date') }}</th>
                            <th class="px-6 py-3">{{ __('Time') }}</th>
                            <th class="px-6 py-3">{{ __('Patient') }}</th>
                            <th class="px-6 py-3">{{ __('Contact') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rows as $row)
                            @php
                                $phone = (string) ($row->family?->phone ?? '');
                                $digits = $this->dialDigits($row->family?->phone);
                            @endphp
                            <tr wire:key="apt-contact-{{ $row->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                                    {{ $row->appointment_date->translatedFormat('M j, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap tabular-nums text-zinc-700 dark:text-zinc-300">
                                    {{ \Carbon\Carbon::parse($row->appointment_time)->format('g:i A') }}
                                </td>
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">
                                    {{ $row->patient?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($phone !== '')
                                        <span class="font-mono text-zinc-800 dark:text-zinc-200">{{ $phone }}</span>
                                    @else
                                        <flux:text class="text-zinc-400">{{ __('No phone on file') }}</flux:text>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if ($row->status === \App\Enums\AppointmentStatus::Booked)
                                        <flux:badge color="rose">{{ __('Booked') }}</flux:badge>
                                    @else
                                        <flux:badge color="sky">{{ __('Arrived') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-end">
                                    <flux:button.group>
                                        @if ($digits !== '')
                                            <flux:button size="sm" variant="ghost" icon="phone" :href="'tel:'.$digits">
                                                {{ __('Call') }}
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" variant="ghost" icon="phone" disabled>
                                                {{ __('Call') }}
                                            </flux:button>
                                        @endif
                                        @if ($phone !== '')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="clipboard-document"
                                                x-data
                                                x-on:click="navigator.clipboard.writeText(@js($phone))"
                                            >
                                                {{ __('Copy') }}
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" variant="ghost" icon="clipboard-document" disabled>
                                                {{ __('Copy') }}
                                            </flux:button>
                                        @endif
                                    </flux:button.group>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
