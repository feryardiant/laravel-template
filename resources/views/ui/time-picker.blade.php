<div class="space-y-8">
    <div class="space-y-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Time picker</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Popover hour and minute columns with single, multiple and 12-hour modes.
        </p>
    </div>

    <section data-ui-demo="time-picker-single" class="max-w-xs space-y-3">
        <x-ui.time-picker
            wire:model.live="time"
            label="Start time"
            interval="30"
            min="08:00"
            max="18:00"
            clearable
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="time">{{ $time }}</span>
        </p>

        <flux:button size="sm" wire:click="moveTime" data-ui-test="move-time">Set time to 15:30</flux:button>
    </section>

    <section data-ui-demo="time-picker-12h" class="max-w-xs space-y-3">
        <x-ui.time-picker
            wire:model.live="time12"
            label="Twelve hour"
            time-format="12-hour"
            locale="en-US"
            clearable
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="time12">{{ $time12 }}</span>
        </p>
    </section>

    <section data-ui-demo="time-picker-multiple" class="max-w-xs space-y-3">
        <x-ui.time-picker wire:model.live="times" label="Meeting times" multiple interval="30" clearable />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="times">{{ implode(', ', $times) }}</span>
        </p>
    </section>

    <section data-ui-demo="time-picker-constrained" class="max-w-xs space-y-3">
        <x-ui.time-picker
            label="Constrained"
            value="10:00"
            interval="15"
            min="09:00"
            max="17:00"
            :unavailable="['12:00-12:59']"
            clearable
        />
    </section>

    <section data-ui-demo="time-picker-off-step" class="max-w-xs space-y-3">
        <x-ui.time-picker label="Off step" value="09:17" open-to="09:17" interval="30" />
    </section>

    <section data-ui-demo="time-picker-off-step-multiple" class="max-w-xs space-y-3">
        <x-ui.time-picker multiple label="Off step multiple" open-to="09:17" interval="30" />
    </section>

    <section data-ui-demo="time-picker-plain" class="max-w-xs space-y-3">
        <form method="get" action="{{ route('ui.time-picker') }}" class="space-y-4">
            <x-ui.time-picker name="plain_time" value="14:30" label="Plain time" interval="30" />
            <x-ui.time-picker
                name="plain_times"
                multiple
                :value="['09:00', '16:00']"
                label="Plain times"
                interval="30"
            />
        </form>
    </section>

    <section data-ui-demo="time-picker-disabled" class="max-w-xs space-y-3">
        <x-ui.time-picker value="10:00" disabled />
    </section>
</div>
