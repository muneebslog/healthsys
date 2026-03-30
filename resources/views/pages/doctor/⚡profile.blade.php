<?php

use App\Livewire\Concerns\GuardsDoctorAccess;
use App\Models\Doctor;
use App\Models\ServicePrice;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My profile')] class extends Component
{
    use GuardsDoctorAccess;

    public Doctor $doctor;

    public function mount(): void
    {
        $this->doctor = $this->doctorProfile();
    }

    #[Computed]
    public function servicePrices()
    {
        return ServicePrice::query()
            ->where('doctor_id', $this->doctor->id)
            ->where('is_active', true)
            ->with('service:id,name')
            ->orderBy('service_id')
            ->get();
    }

    protected function formatTime(?string $t): string
    {
        if ($t === null || $t === '') {
            return '—';
        }

        return \Illuminate\Support\Carbon::parse('2000-01-01 '.$t)->format('g:i A');
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-teal-50/35 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-teal-950/20">
        <div class="pointer-events-none absolute -end-16 -top-16 size-48 rounded-full bg-teal-400/10 blur-3xl dark:bg-teal-500/10"></div>
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Profile') }}
                </flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('How patients and reception see you. Contact admin to update schedule or pricing.') }}
                </flux:text>
            </div>
            <flux:badge color="teal" class="shrink-0">{{ __('Read-only') }}</flux:badge>
        </div>
    </header>

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="border-zinc-200 dark:border-zinc-700">
                <div class="flex items-start gap-4">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-teal-100 text-lg font-semibold text-teal-900 dark:bg-teal-950/60 dark:text-teal-200">
                        {{ str($doctor->name)->explode(' ')->take(2)->map(fn ($w) => str($w)->substr(0, 1))->implode('') }}
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-white">{{ $doctor->name }}</flux:heading>
                        @if ($doctor->specialization)
                            <flux:text class="mt-0.5 text-zinc-600 dark:text-zinc-400">{{ $doctor->specialization }}</flux:text>
                        @endif
                    </div>
                </div>
                <dl class="mt-6 space-y-3 border-t border-zinc-100 pt-6 text-sm dark:border-zinc-800">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Phone') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-white">{{ $doctor->phone ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Status') }}</dt>
                        <dd class="font-medium capitalize text-zinc-900 dark:text-white">{{ $doctor->status }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('On payroll') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-white">{{ $doctor->is_on_payroll ? __('Yes') : __('No') }}</dd>
                    </div>
                </dl>
            </flux:card>
        </div>

        <div class="space-y-6 lg:col-span-3">
            <flux:card class="border-zinc-200 dark:border-zinc-700">
                <flux:heading size="md" class="mb-4">{{ __('Appointment hours') }}</flux:heading>
                @if ($doctor->hasWorkingHours())
                    <div class="flex flex-wrap items-baseline gap-2 text-zinc-800 dark:text-zinc-200">
                        <flux:text class="tabular-nums font-medium">{{ $this->formatTime($doctor->start_time) }}</flux:text>
                        <flux:text class="text-zinc-400">—</flux:text>
                        <flux:text class="tabular-nums font-medium">{{ $this->formatTime($doctor->end_time) }}</flux:text>
                    </div>
                @else
                    <flux:callout color="zinc" icon="information-circle">
                        {{ __('No bookable hours set. Slots will not appear on the appointments calendar until admin sets start and end times.') }}
                    </flux:callout>
                @endif
            </flux:card>

            <div>
                <flux:heading size="md" class="mb-4">{{ __('Services & share') }}</flux:heading>
                @if ($this->servicePrices->isEmpty())
                    <flux:card class="border-zinc-200 dark:border-zinc-700">
                        <flux:text class="text-zinc-500">{{ __('No active service prices linked to you yet.') }}</flux:text>
                    </flux:card>
                @else
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                        <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            <thead class="bg-zinc-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Service') }}</th>
                                    <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Price') }}</th>
                                    <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Your %') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($this->servicePrices as $row)
                                    <tr wire:key="sp-{{ $row->id }}">
                                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $row->service?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-end tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format((int) $row->price) }}</td>
                                        <td class="px-4 py-3 text-end tabular-nums text-teal-800 dark:text-teal-300">{{ $row->doctor_share }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
