{{--
    $total (int), $breakdown (Collection of { doctor_name, total_share }), $showShareOutLink (bool)
--}}
<section
    aria-labelledby="todays-doctor-payout-heading"
    class="relative overflow-hidden rounded-2xl border border-teal-200/70 bg-gradient-to-br from-white via-teal-50/30 to-cyan-50/40 shadow-sm dark:border-teal-900/40 dark:from-zinc-900 dark:via-zinc-900 dark:to-teal-950/30"
>
    <div
        class="pointer-events-none absolute inset-0 opacity-[0.07] dark:opacity-[0.12]"
        style="background-image: linear-gradient(135deg, currentColor 1px, transparent 1px); background-size: 14px 14px;"
    ></div>
    <div class="pointer-events-none absolute -end-24 -top-20 size-64 rounded-full bg-cyan-400/15 blur-3xl dark:bg-cyan-500/10"></div>

    <div class="relative grid gap-6 p-6 sm:grid-cols-[minmax(0,1fr)_minmax(0,280px)] sm:items-start sm:gap-8 lg:p-8">
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <flux:icon.banknotes class="size-5 text-teal-700 dark:text-teal-400" />
                <flux:heading id="todays-doctor-payout-heading" size="lg" class="text-zinc-900 dark:text-white">
                    {{ __("Today's doctor payouts") }}
                </flux:heading>
            </div>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Cash paid out to doctors from “Log & Pay” runs, for') }}
                <time datetime="{{ now()->toDateString() }}" class="font-medium text-zinc-800 dark:text-zinc-200">
                    {{ now()->timezone(config('app.timezone'))->translatedFormat('l, M j') }}
                </time>.
            </flux:text>
            <p class="text-4xl font-semibold tracking-tight tabular-nums text-teal-900 dark:text-teal-100 sm:text-5xl">
                {{ number_format($total) }}
            </p>
            @if ($showShareOutLink)
                <div class="pt-1">
                    <flux:button
                        :href="route('reception.doctor-share-out')"
                        variant="outline"
                        size="sm"
                        icon="arrow-top-right-on-square"
                        wire:navigate
                        class="border-teal-300/80 text-teal-900 hover:bg-teal-50 dark:border-teal-800 dark:text-teal-100 dark:hover:bg-teal-950/40"
                    >
                        {{ __('Doctor share out') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-teal-200/60 bg-white/80 backdrop-blur-sm dark:border-teal-900/50 dark:bg-zinc-950/40">
            <div class="border-b border-teal-100/80 px-4 py-3 dark:border-teal-900/40">
                <flux:text class="text-xs font-semibold uppercase tracking-wide text-teal-800 dark:text-teal-300">
                    {{ __('By doctor') }}
                </flux:text>
            </div>
            @if ($breakdown->isEmpty())
                <div class="px-4 py-8 text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No payouts recorded for this date yet.') }}
                    </flux:text>
                </div>
            @else
                <ul class="max-h-56 divide-y divide-teal-100/80 overflow-y-auto dark:divide-teal-900/40">
                    @foreach ($breakdown as $row)
                        <li
                            wire:key="payout-doc-{{ $loop->index }}-{{ $row->doctor_name }}"
                            class="flex items-center justify-between gap-3 px-4 py-2.5"
                        >
                            <flux:text class="min-w-0 truncate font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $row->doctor_name }}
                            </flux:text>
                            <flux:text class="shrink-0 tabular-nums text-zinc-700 dark:text-zinc-300">
                                {{ number_format($row->total_share) }}
                            </flux:text>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
