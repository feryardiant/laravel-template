<div class="space-y-8">
    <div class="space-y-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Calendar</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Dependency-free Gregorian calendar with single, multiple and range selection.
        </p>
    </div>

    <section data-ui-demo="calendar-single" class="space-y-3">
        <x-ui.calendar wire:model.live="date" label="Single date" with-today />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="date">{{ $date }}</span>
        </p>

        <flux:button size="sm" wire:click="moveSingleDate" data-ui-test="set-date">Set date +5 days</flux:button>
    </section>

    <section data-ui-demo="calendar-multiple" class="space-y-3">
        <x-ui.calendar wire:model.live="dates" mode="multiple" label="Multiple dates" fixed-weeks />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="dates">{{ implode(', ', $dates) }}</span>
        </p>
    </section>

    <section data-ui-demo="calendar-range" class="space-y-3">
        <x-ui.calendar
            wire:model.live="range"
            mode="range"
            label="Date range"
            months="2"
            min-range="2"
            max-range="10"
            :unavailable="[now()->addDays(1)->format('Y-m-d')]"
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value:
            <span data-ui-bound="range">{{ $range['start'] }} / {{ $range['end'] }}</span>
        </p>
    </section>

    <section data-ui-demo="calendar-carbon" class="space-y-3">
        <x-ui.calendar wire:model.live="carbonDate" label="Carbon date" />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="carbon-date">{{ $carbonDate?->format('Y-m-d') }}</span>
        </p>
    </section>

    <section data-ui-demo="calendar-typed-range" class="space-y-3">
        <x-ui.calendar wire:model.live="typedRange" mode="range" label="Typed DateRange" />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value:
            <span data-ui-bound="typed-range">{{ $typedRange?->start()?->format('Y-m-d') }} / {{ $typedRange?->end()?->format('Y-m-d') }}</span>
        </p>
    </section>

    <section data-ui-demo="calendar-constrained" class="space-y-3">
        <x-ui.calendar
            label="Constrained"
            min="today"
            :max="now()->addDays(20)->format('Y-m-d')"
            :unavailable="[now()->addDays(2)->format('Y-m-d')]"
            start-day="1"
            fixed-weeks
        />
    </section>

    <section data-ui-demo="calendar-static" class="space-y-3">
        <x-ui.calendar label="Static" static :navigation="false" fixed-weeks />
    </section>

    <section data-ui-demo="calendar-open-to" class="space-y-3">
        <x-ui.calendar
            label="Open to next month"
            :open-to="now()->addMonth()->startOfMonth()->format('Y-m-d')"
        />
    </section>

    <section data-ui-demo="calendar-leap" class="space-y-3">
        <x-ui.calendar label="Leap February" open-to="2024-02-01" />
    </section>

    <section data-ui-demo="calendar-four-weeks" class="space-y-3">
        <x-ui.calendar label="Four week February" open-to="2026-02-01" />
    </section>

    <section data-ui-demo="calendar-en" class="space-y-3">
        <x-ui.calendar label="Canadian English week start" locale="en-CA" open-to="2026-02-01" />
    </section>

    <section data-ui-demo="calendar-fallback-sunday" class="space-y-3">
        <x-ui.calendar label="Philippines week start" locale="en-PH" open-to="2026-02-01" />
    </section>

    <section data-ui-demo="calendar-fallback-en" class="space-y-3">
        <x-ui.calendar label="Generic English week start" locale="en" open-to="2026-02-01" />
    </section>

    <section data-ui-demo="calendar-fallback-monday" class="space-y-3">
        <x-ui.calendar label="UK week start" locale="en-GB" open-to="2026-02-01" />
    </section>

    <section data-ui-demo="calendar-fallback-ar-sa" class="space-y-3">
        <x-ui.calendar label="Saudi week start" locale="ar-SA" open-to="2026-02-01" />
    </section>

    <section data-ui-demo="calendar-fallback-ar-eg" class="space-y-3">
        <x-ui.calendar label="Egypt week start" locale="ar-EG" open-to="2026-02-01" />
    </section>

    <section data-ui-demo="calendar-plain" class="space-y-3">
        <form method="get" action="{{ route('ui.calendar') }}">
            <x-ui.calendar name="plain_date" value="2024-02-15" open-to="2024-02-01" label="Plain date" />
            <x-ui.calendar
                name="plain_dates"
                mode="multiple"
                :value="['2024-02-10', '2024-02-12']"
                open-to="2024-02-01"
                label="Plain multiple"
            />
            <x-ui.calendar
                name="plain_range"
                mode="range"
                value="2024-02-10/2024-02-14"
                open-to="2024-02-01"
                label="Plain range"
            />
        </form>
    </section>

    <section data-ui-demo="calendar-long-range" class="space-y-3">
        <x-ui.calendar
            mode="range"
            label="Long range"
            :unavailable="[now()->addMonths(6)->format('Y-m-d')]"
        />
    </section>

    <section data-ui-demo="calendar-invalid" class="space-y-3">
        <x-ui.calendar label="Invalid" :value="now()->format('Y-m-d')" invalid />
    </section>

    <section data-ui-demo="calendar-bounds" class="space-y-3">
        <x-ui.calendar
            label="Bounds"
            open-to="2024-02-01"
            min="2024-02-10"
            max="2024-02-20"
            unavailable="2024-02-15"
        />
    </section>
</div>
