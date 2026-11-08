<div class="space-y-8">
    <div class="space-y-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Date and time picker</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Calendar and time columns in one popover, with single and range modes.
        </p>
    </div>

    <section data-ui-demo="datetime-picker-single" class="max-w-sm space-y-3">
        <x-ui.datetime-picker
            wire:model.live="startAt"
            label="Starts at"
            default-time="09:00"
            clearable
            with-today
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="start-at">{{ $startAt }}</span>
        </p>

        <flux:button size="sm" wire:click="moveStartAt" data-ui-test="move-start">Set to 10 Nov 14:30</flux:button>
    </section>

    <section data-ui-demo="datetime-picker-empty" class="max-w-sm space-y-3">
        <x-ui.datetime-picker wire:model.live="emptyAt" label="Empty starts at" default-time="09:00" clearable />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="empty-at">{{ $emptyAt }}</span>
        </p>
    </section>

    <section data-ui-demo="datetime-picker-confirm" class="max-w-sm space-y-3">
        <x-ui.datetime-picker
            wire:model.live="confirmedAt"
            label="Confirmed starts at"
            default-time="09:00"
            with-confirmation
            clearable
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="confirmed-at">{{ $confirmedAt }}</span>
        </p>
    </section>

    <section data-ui-demo="datetime-picker-range-empty" class="max-w-md space-y-3">
        <x-ui.datetime-picker
            mode="range"
            label="Empty schedule"
            default-time="09:00/17:00"
            with-confirmation
        />
    </section>

    <section data-ui-demo="datetime-picker-range" class="max-w-md space-y-3">
        <x-ui.datetime-picker
            wire:model.live="schedule"
            mode="range"
            label="Schedule"
            default-time="09:00/17:00"
            with-confirmation
            clearable
            with-today
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value:
            <span data-ui-bound="schedule">{{ $schedule['start'] }} / {{ $schedule['end'] }}</span>
        </p>
    </section>

    <section data-ui-demo="datetime-picker-same-day" class="max-w-md space-y-3">
        <x-ui.datetime-picker
            mode="range"
            label="Same-day range"
            value="2026-11-08T09:00/2026-11-08T17:00"
            open-to="2026-11-01"
            default-time="09:00/17:00"
        />
    </section>

    <section data-ui-demo="datetime-picker-constrained" class="max-w-md space-y-3">
        <x-ui.datetime-picker
            label="Constrained"
            value="2026-02-15T10:00"
            open-to="2026-02-01"
            min="2026-02-10T09:00"
            max="2026-02-20T17:00"
            min-time="09:00"
            max-time="17:00"
            :unavailable="['2026-02-17', '12:00-13:00', '2026-02-18T10:30']"
            clearable
        />
    </section>

    <section data-ui-demo="datetime-picker-plain" class="max-w-md space-y-3">
        <form method="get" action="{{ route('ui.datetime-picker') }}" class="space-y-4">
            <x-ui.datetime-picker name="plain_at" value="2024-02-15T14:30" label="Plain datetime" />
            <x-ui.datetime-picker
                name="plain_schedule"
                mode="range"
                value="2024-02-10T09:00/2024-02-12T17:00"
                label="Plain datetime range"
            />
        </form>
    </section>

    <section data-ui-demo="datetime-picker-disabled" class="max-w-sm space-y-3">
        <x-ui.datetime-picker value="2026-11-08T10:30" disabled />
    </section>
</div>
