<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
    <header class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-linear-to-br from-zinc-50 via-white to-emerald-50/25 p-8 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950/25">
        <div class="pointer-events-none absolute -inset-e-12 top-0 size-40 rounded-full bg-emerald-400/10 blur-3xl dark:bg-emerald-500/10"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl" class="tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Role-based dashboard') }}
                </flux:heading>
                <flux:text class="mt-1 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    {{ __('Pick a section. Cards are enabled based on your account role.') }}
                </flux:text>
            </div>
            <flux:badge :color="$roleBadgeColor" class="shrink-0">
                {{ __('Your role') }}: {{ $roleLabel }}
            </flux:badge>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($sections as $section)
            <section class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="{{ $section['headerGradientClass'] }} border-b border-zinc-200/60 px-6 py-5 dark:border-zinc-800/60">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <flux:heading size="lg" class="tracking-tight text-zinc-900 dark:text-white">
                                {{ $section['title'] }}
                            </flux:heading>
                            <flux:text class="mt-1 block text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $section['description'] }}
                            </flux:text>
                        </div>
                        @php($anyEnabled = collect($section['items'])->contains(fn ($i) => $i['enabled'] ?? false))
                        @if ($anyEnabled)
                            <flux:badge :color="$section['badgeColor']" class="shrink-0">
                                {{ __('Available') }}
                            </flux:badge>
                        @else
                            <flux:badge color="zinc" class="shrink-0">
                                {{ __('No access') }}
                            </flux:badge>
                        @endif
                    </div>
                </div>

                <div class="p-6">
                    @if (empty($section['items']))
                        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 p-6 text-center dark:border-zinc-600 dark:bg-zinc-800/40">
                            <flux:heading size="md">{{ __('Coming soon') }}</flux:heading>
                            <flux:text class="mt-2 block text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('No dashboard cards for this section yet.') }}
                            </flux:text>
                        </div>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($section['items'] as $item)
                                @if (($item['enabled'] ?? false) === true)
                                    <a
                                        href="{{ $item['href'] }}"
                                        wire:navigate
                                        class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300/80 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                                    >
                                        <div class="pointer-events-none absolute -inset-e-8 -top-10 size-24 rounded-full bg-zinc-400/10 blur-2xl transition group-hover:bg-zinc-400/20"></div>
                                        <flux:heading size="md" class="relative tabular-nums text-zinc-900 dark:text-white">
                                            {{ $item['label'] }}
                                        </flux:heading>
                                        <flux:text class="relative mt-1 block text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ $item['description'] }}
                                        </flux:text>
                                    </a>
                                @else
                                    <div
                                        aria-disabled="true"
                                        class="group relative cursor-not-allowed select-none overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 opacity-60 shadow-xs dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <div class="pointer-events-none absolute -inset-e-8 -top-10 size-24 rounded-full bg-zinc-400/10 blur-2xl"></div>
                                        <flux:heading size="md" class="relative text-zinc-500 dark:text-zinc-400">
                                            {{ $item['label'] }}
                                        </flux:heading>
                                        <flux:text class="relative mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Locked') }}
                                        </flux:text>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</div>

