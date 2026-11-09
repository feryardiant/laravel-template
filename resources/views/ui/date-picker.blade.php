<div class="space-y-8">
    <div class="space-y-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Date picker</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Readonly trigger with a popover calendar, ranges, presets and a confirmation footer.
        </p>
    </div>

    <section data-ui-demo="date-picker-single" class="max-w-sm space-y-3">
        <x-ui.date-picker wire:model.live="date" label="Publish date" clearable with-today />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="date">{{ $date }}</span>
        </p>

        <flux:button size="sm" wire:click="moveDate" data-ui-test="move-date">Set date +5 days</flux:button>
    </section>

    <section data-ui-demo="date-picker-confirm" class="max-w-sm space-y-3">
        <x-ui.date-picker
            wire:model.live="confirmed"
            label="Confirmed date"
            clearable
            with-confirmation
            with-today
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="confirmed">{{ $confirmed }}</span>
        </p>
    </section>

    <section data-ui-demo="date-picker-range" class="max-w-md space-y-3">
        <x-ui.date-picker
            wire:model.live="range"
            mode="range"
            label="Report range"
            clearable
            with-presets
            presets="today yesterday thisWeek last7Days thisMonth yearToDate allTime"
            :min="now()->startOfYear()->format('Y-m-d')"
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value:
            <span data-ui-bound="range">{{ $range['start'] }} / {{ $range['end'] }}</span>
        </p>
    </section>

    <section data-ui-demo="date-picker-single-presets" class="max-w-sm space-y-3">
        <x-ui.date-picker
            label="Single with presets"
            with-presets
            presets="today yesterday thisWeek last7Days"
            min-range="2"
            max-range="2"
        />
    </section>

    <section data-ui-demo="date-picker-preset-constraints" class="max-w-sm space-y-3">
        <x-ui.date-picker
            mode="range"
            label="Constrained presets"
            with-presets
            presets="today yesterday last7Days thisMonth"
            max-range="2"
        />
    </section>

    <section data-ui-demo="date-picker-preset-unavailable" class="max-w-sm space-y-3">
        <x-ui.date-picker
            mode="range"
            label="Unavailable preset"
            with-presets
            presets="today yesterday"
            :unavailable="[now()->subDay()->format('Y-m-d')]"
        />
    </section>

    <section data-ui-demo="date-picker-preset-empty" class="max-w-sm space-y-3">
        <x-ui.date-picker
            mode="range"
            label="Empty presets"
            with-presets
            presets="today yesterday"
            :unavailable="[now()->format('Y-m-d'), now()->subDay()->format('Y-m-d')]"
        />
    </section>

    <section data-ui-demo="date-picker-preset-alltime" class="max-w-sm space-y-3">
        <x-ui.date-picker
            mode="range"
            label="All time without min"
            with-presets
            presets="allTime today"
            :unavailable="[now()->format('Y-m-d')]"
        />
    </section>

    <section data-ui-demo="date-picker-out-of-bounds" class="max-w-sm space-y-3">
        <x-ui.date-picker
            label="Out of bounds"
            value="2026-01-01"
            open-to="2026-01-01"
            min="2026-02-10"
        />
    </section>

    <section data-ui-demo="date-picker-bounded" class="max-w-sm space-y-3">
        <x-ui.date-picker
            label="Bounded"
            value="2026-02-15"
            open-to="2026-02-01"
            min="2026-02-10"
            max="2026-02-20"
            unavailable="2026-02-17"
            clearable
        />
    </section>

    <section data-ui-demo="date-picker-plain" class="max-w-sm space-y-3">
        <form method="get" action="{{ route('ui.date-picker') }}" class="space-y-4">
            <x-ui.date-picker name="plain_date" value="2024-02-15" open-to="2024-02-01" label="Plain date" />
            <x-ui.date-picker
                name="plain_range"
                mode="range"
                value="2024-02-10/2024-02-14"
                open-to="2024-02-01"
                label="Plain range"
            />
        </form>
    </section>

    <section data-ui-demo="date-picker-disabled" class="max-w-sm space-y-3">
        <x-ui.date-picker value="2026-02-15" disabled />
    </section>

    <section data-ui-demo="date-picker-invalid" class="max-w-sm space-y-3">
        <x-ui.date-picker label="Invalid date" value="2026-02-15" open-to="2026-02-01" invalid />
    </section>
</div>
