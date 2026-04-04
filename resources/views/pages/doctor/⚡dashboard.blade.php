<?php

use App\Livewire\Concerns\GuardsDoctorAccess;
use App\Models\Doctor;
use App\Models\InvoiceService;
use App\Models\Queue;
use App\Models\QueueToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Doctor home')] class extends Component
{
    use GuardsDoctorAccess;

    public Doctor $doctor;

    public function mount(): void
    {
        $this->doctor = $this->doctorProfile();
    }

    #[Computed]
    public function pendingShareTotal(): int
    {
        return (int) InvoiceService::query()
            ->unpaidDoctorShare()
            ->where('doctor_id', $this->doctor->id)
            ->sum('doctor_share_amount');
    }

    #[Computed]
    public function todayTokenCount(): int
    {
        return QueueToken::query()
            ->whereHas('queue', fn ($q) => $q->where('doctor_id', $this->doctor->id))
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    #[Computed]
    public function activeQueuesForDoctor()
    {
        return Queue::query()
            ->active()
            ->where('doctor_id', $this->doctor->id)
            ->with('service:id,name')
            ->orderBy('id')
            ->get();
    }

    protected function formatMoney(int $amount): string
    {
        return number_format($amount);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 via-white to-teal-50/40 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-teal-950/25">
        <div class="pointer-events-none absolute -end-20 -top-20 size-56 rounded-full bg-teal-400/10 blur-3xl dark:bg-teal-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Welcome, :name', ['name' => $doctor->name]) }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Your schedule, earnings, and today’s queue in one place.') }}
                </flux:text>
            </div>
            <flux:badge color="teal" class="shrink-0">{{ __('Doctor') }}</flux:badge>
        </div>
    </header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a
            href="{{ route('doctor.payouts') }}"
            wire:navigate
            class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:border-teal-300/80 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-600/50"
        >
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-teal-400/5 blur-2xl transition group-hover:bg-teal-400/10"></div>
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Unpaid share (owed)') }}</flux:text>
            <flux:heading size="lg" class="mt-2 tabular-nums text-teal-900 dark:text-teal-200">
                {{ $this->formatMoney($this->pendingShareTotal) }}
            </flux:heading>
            <flux:text class="mt-3 text-sm text-teal-700/90 dark:text-teal-300/90">{{ __('View payout details →') }}</flux:text>
        </a>

        <a
            href="{{ route('doctor.queue') }}"
            wire:navigate
            class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:border-teal-300/80 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-600/50"
        >
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-teal-400/5 blur-2xl transition group-hover:bg-teal-400/10"></div>
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Tokens today') }}</flux:text>
            <flux:heading size="lg" class="mt-2 tabular-nums text-zinc-900 dark:text-white">
                {{ $this->todayTokenCount }}
            </flux:heading>
            <flux:text class="mt-3 text-sm text-teal-700/90 dark:text-teal-300/90">{{ __('Open today’s queue →') }}</flux:text>
        </a>

        <a
            href="{{ route('doctor.profile') }}"
            wire:navigate
            class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:border-teal-300/80 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-600/50"
        >
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-teal-400/5 blur-2xl transition group-hover:bg-teal-400/10"></div>
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Profile') }}</flux:text>
            <flux:heading size="lg" class="mt-2 line-clamp-2 text-zinc-900 dark:text-white">
                {{ $doctor->specialization ?: __('Your services & hours') }}
            </flux:heading>
            <flux:text class="mt-3 text-sm text-teal-700/90 dark:text-teal-300/90">{{ __('View profile →') }}</flux:text>
        </a>

        <a
            href="{{ route('doctor.processes') }}"
            wire:navigate
            class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:border-cyan-300/80 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-cyan-600/50"
        >
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-cyan-400/5 blur-2xl transition group-hover:bg-cyan-400/10"></div>
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Processes') }}</flux:text>
            <flux:heading size="lg" class="mt-2 text-zinc-900 dark:text-white">
                {{ __('OT & procedures') }}
            </flux:heading>
            <flux:text class="mt-3 text-sm text-cyan-800/90 dark:text-cyan-300/90">{{ __('View by week or date range →') }}</flux:text>
        </a>
    </div>

    @if ($this->activeQueuesForDoctor->isNotEmpty())
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="md" class="mb-4">{{ __('Active queues (your services)') }}</flux:heading>
            <ul class="flex flex-wrap gap-2">
                @foreach ($this->activeQueuesForDoctor as $q)
                    <li wire:key="aq-{{ $q->id }}">
                        <flux:badge color="zinc">{{ $q->service?->name ?? __('Service') }} · {{ __('Now') }} T-{{ $q->current_flow_token }}</flux:badge>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
