@props([
    'hours',
    'minutes',
    'activeHour',
    'activeMinute',
    'hourKeydown',
    'minuteKeydown',
    'selectHour',
    'selectMinute',
    'hourId',
    'minuteId',
    'boundIds' => false,
    'isAm' => 'false',
    'isPm' => 'false',
    'meridiemWhen' => 'hour12',
    'onSelectAm' => null,
    'onSelectPm' => null,
    'optionClass' => 'h-8',
    'columnClass' => 'w-16 [@media(hover:none)]:flex-1',
    'listClass' => 'max-h-56 overflow-y-auto scroll-py-2',
    'headerClass' => 'px-2 py-1',
    'meridiemWrapperClass' => 'flex flex-col gap-1 pt-6 [@media(hover:none)]:flex-1',
    'meridiemClass' => 'flex h-8 min-w-12 items-center justify-center rounded-md px-2 text-sm font-medium transition-colors [@media(hover:none)]:min-h-11',
    'panelClass' => 'flex gap-1',
])

@php
    $hourLabel = __('ui.time-picker.hour');
    $minuteLabel = __('ui.time-picker.minute');
@endphp

{{-- Presentational hour/minute columns. State, bounds and unavailable rules
     stay with the consuming component. --}}
<div class="{{ $panelClass }}">
    <div class="{{ $columnClass }}">
        <div data-ui-hour-label class="{{ $headerClass }} text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">
            {{ $hourLabel }}
        </div>

        <div
            @if ($boundIds) x-bind:id="{{ $hourId }}" @else id="{{ $hourId }}" @endif
            data-ui-hours
            data-column="hour"
            role="listbox"
            tabindex="0"
            aria-label="{{ $hourLabel }}"
            x-bind:aria-activedescendant="{{ $activeHour }}"
            x-on:keydown="{{ $hourKeydown }}"
            class="{{ $listClass }}"
        >
            <template x-for="option in {{ $hours }}" :key="option.id">
                <div
                    role="option"
                    :id="option.id"
                    :data-hour="option.value"
                    :aria-selected="option.selected ? 'true' : 'false'"
                    :aria-disabled="option.disabled || null"
                    x-on:click="{{ $selectHour }}"
                    :class="option.disabled
                        ? 'cursor-default text-zinc-300 dark:text-zinc-600'
                        : option.selected
                            ? 'cursor-pointer bg-accent text-accent-foreground'
                            : 'cursor-pointer text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                    class="flex {{ $optionClass }} items-center justify-center rounded-md text-sm tabular-nums transition-colors [@media(hover:none)]:min-h-11"
                    x-text="option.label"
                ></div>
            </template>
        </div>
    </div>

    <div class="{{ $columnClass }}">
        <div data-ui-minute-label class="{{ $headerClass }} text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">
            {{ $minuteLabel }}
        </div>

        <div
            @if ($boundIds) x-bind:id="{{ $minuteId }}" @else id="{{ $minuteId }}" @endif
            data-ui-minutes
            data-column="minute"
            role="listbox"
            tabindex="0"
            aria-label="{{ $minuteLabel }}"
            x-bind:aria-activedescendant="{{ $activeMinute }}"
            x-on:keydown="{{ $minuteKeydown }}"
            class="{{ $listClass }}"
        >
            <template x-for="option in {{ $minutes }}" :key="option.id">
                <div
                    role="option"
                    :id="option.id"
                    :data-minute="option.value"
                    :aria-selected="option.selected ? 'true' : 'false'"
                    :aria-disabled="option.disabled || null"
                    x-on:click="{{ $selectMinute }}"
                    :class="option.disabled
                        ? 'cursor-default text-zinc-300 dark:text-zinc-600'
                        : option.selected
                            ? 'cursor-pointer bg-accent text-accent-foreground'
                            : 'cursor-pointer text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                    class="flex {{ $optionClass }} items-center justify-center rounded-md text-sm tabular-nums transition-colors [@media(hover:none)]:min-h-11"
                    x-text="option.label"
                ></div>
            </template>
        </div>
    </div>

    @if ($onSelectAm !== null)
        <div x-show="{{ $meridiemWhen }}" x-cloak class="{{ $meridiemWrapperClass }}">
            <button
                type="button"
                x-on:click="{{ $onSelectAm }}"
                x-bind:aria-pressed="{{ $isAm }} ? 'true' : 'false'"
                x-bind:class="{{ $isAm }} ? 'bg-accent text-accent-foreground' : 'text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                class="{{ $meridiemClass }}"
            >{{ __('ui.time-picker.am') }}</button>

            <button
                type="button"
                x-on:click="{{ $onSelectPm }}"
                x-bind:aria-pressed="{{ $isPm }} ? 'true' : 'false'"
                x-bind:class="{{ $isPm }} ? 'bg-accent text-accent-foreground' : 'text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                class="{{ $meridiemClass }}"
            >{{ __('ui.time-picker.pm') }}</button>
        </div>
    @endif
</div>
