@props([
    'mode' => 'single',
    'value' => null,
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'badge' => null,
    'placeholder' => null,
    'size' => null,
    'interval' => 30,
    'timeFormat' => 'auto',
    'min' => null,
    'max' => null,
    'minTime' => null,
    'maxTime' => null,
    'unavailable' => null,
    'defaultTime' => null,
    'minRange' => null,
    'maxRange' => null,
    'months' => null,
    'withConfirmation' => false,
    'clearable' => false,
    'withToday' => false,
    'startDay' => null,
    'fixedWeeks' => false,
    'openTo' => null,
    'locale' => null,
    'disabled' => false,
    'invalid' => null,
])

@php
    $wireModel = $attributes->wire('model');
    $model = $wireModel->value();
    $model = is_string($model) && $model !== '' ? $model : null;
    $live = $wireModel->hasModifier('live');
    $name = $attributes->get('name');
    $invalid ??= $model !== null && $errors->has($model);

    $mode = in_array($mode, ['single', 'range'], true) ? $mode : 'single';
    $isRange = $mode === 'range';
    $resolvedLocale = $locale ?? app()->getLocale();
    $resolvedMonths = min(2, max(1, (int) ($months ?? ($isRange ? 2 : 1))));
    $format = in_array($timeFormat, ['auto', '12-hour', '24-hour'], true) ? $timeFormat : 'auto';
    $resolvedInterval = min(60, max(1, (int) $interval));
    $resolvedStartDay = $startDay === null ? null : min(6, max(0, (int) $startDay));

    $now = now();
    $resolvedMin = match (true) {
        $min === 'now' => $now->format('Y-m-d\TH:i'),
        $min === 'today' => $now->format('Y-m-d').'T00:00',
        default => \App\View\Value::datetime($min),
    };
    $resolvedMax = match (true) {
        $max === 'now' => $now->format('Y-m-d\TH:i'),
        $max === 'today' => $now->format('Y-m-d').'T23:59',
        default => \App\View\Value::datetime($max),
    };

    if ($resolvedMax !== null && is_string($max) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($max)) === 1) {
        $resolvedMax = substr($resolvedMax, 0, 10).'T23:59';
    }

    $minSource = in_array($min, ['now', 'today'], true) ? $min : null;
    $maxSource = in_array($max, ['now', 'today'], true) ? $max : null;
    $openToSource = in_array($openTo, ['now', 'today'], true) ? $openTo : null;

    $minDate = $resolvedMin === null ? null : substr($resolvedMin, 0, 10);
    $maxDate = $resolvedMax === null ? null : substr($resolvedMax, 0, 10);
    $resolvedMinTime = \App\View\Value::time($minTime);
    $resolvedMaxTime = \App\View\Value::time($maxTime);

    $normalizedValue = $isRange ? \App\View\Value::datetimeRange($value) : \App\View\Value::datetime($value);
    $unavailableEntries = \App\View\Value::datetimes($unavailable);
    $unavailableDates = array_values(array_filter(
        $unavailableEntries,
        fn (string $entry) => strlen($entry) === 10 && \App\View\Value::date($entry) !== null,
    ));

    $defaultParts = is_string($defaultTime) ? explode('/', $defaultTime, 2) : [];
    $defaultStartTime = \App\View\Value::time($defaultParts[0] ?? null);
    $defaultEndTime = isset($defaultParts[1]) ? \App\View\Value::time($defaultParts[1]) : null;

    $openToDate = null;
    $openToTime = null;

    if ($openTo === 'now') {
        $openToDate = $now->format('Y-m-d');
        $openToTime = $now->format('H:i');
    } elseif ($openTo === 'today') {
        $openToDate = $now->format('Y-m-d');
    } elseif (($date = \App\View\Value::date($openTo)) !== null) {
        $openToDate = $date;
    } elseif (($datetime = \App\View\Value::datetime($openTo)) !== null) {
        $openToDate = substr($datetime, 0, 10);
        $openToTime = substr($datetime, 11, 5);
    } else {
        $openToTime = \App\View\Value::time($openTo);
    }

    $calendarValue = $isRange
        ? [
            'start' => $normalizedValue['start'] === null ? null : substr($normalizedValue['start'], 0, 10),
            'end' => $normalizedValue['end'] === null ? null : substr($normalizedValue['end'], 0, 10),
        ]
        : ($normalizedValue === null ? null : substr($normalizedValue, 0, 10));

    $key = \Illuminate\Support\Str::random(12);
    $triggerId = 'ui-datetime-picker-'.$key.'-trigger';
    $popoverId = 'ui-datetime-picker-'.$key.'-popover';
    $isPlain = $model === null && $name !== null;
    $formInputId = $isPlain && ! $isRange ? 'ui-datetime-picker-'.$key.'-input' : null;
    $formStartId = $isPlain && $isRange ? 'ui-datetime-picker-'.$key.'-start' : null;
    $formEndId = $isPlain && $isRange ? 'ui-datetime-picker-'.$key.'-end' : null;

    $accessibleLabel = $label ?? $attributes->get('aria-label') ?? $placeholder ?? __('ui.datetime-picker.label');

    $labels = [
        'label' => __('ui.datetime-picker.label'),
        'placeholder' => __('ui.datetime-picker.placeholder'),
        'placeholderRange' => __('ui.datetime-picker.placeholder-range'),
        'start' => __('ui.datetime-picker.start'),
        'end' => __('ui.datetime-picker.end'),
        'at' => __('ui.datetime-picker.at'),
        'clear' => __('ui.datetime-picker.clear'),
        'cancel' => __('ui.datetime-picker.cancel'),
        'select' => __('ui.datetime-picker.select'),
        'selectRange' => __('ui.datetime-picker.select-range'),
        'rangeInvalid' => __('ui.datetime-picker.range-invalid'),
        'hour' => __('ui.time-picker.hour'),
        'minute' => __('ui.time-picker.minute'),
        'am' => __('ui.time-picker.am'),
        'pm' => __('ui.time-picker.pm'),
    ];

    $config = [
        'mode' => $mode,
        'value' => $normalizedValue,
        'model' => $model,
        'live' => $live,
        'locale' => $resolvedLocale,
        'months' => $resolvedMonths,
        'timeFormat' => $format,
        'interval' => $resolvedInterval,
        'min' => $resolvedMin,
        'max' => $resolvedMax,
        'minDate' => $minDate,
        'maxDate' => $maxDate,
        'minSource' => $minSource,
        'maxSource' => $maxSource,
        'openToSource' => $openToSource,
        'minTime' => $resolvedMinTime,
        'maxTime' => $resolvedMaxTime,
        'unavailable' => $unavailableEntries,
        'defaultStartTime' => $defaultStartTime,
        'defaultEndTime' => $defaultEndTime,
        'openToDate' => $openToDate,
        'openToTime' => $openToTime,
        'placeholder' => $placeholder,
        'withConfirmation' => (bool) $withConfirmation,
        'clearable' => (bool) $clearable,
        'disabled' => (bool) $disabled,
        'triggerId' => $triggerId,
        'popoverId' => $popoverId,
        'formInputId' => $formInputId,
        'formStartId' => $formStartId,
        'formEndId' => $formEndId,
        'labels' => $labels,
    ];

    $endpoints = $isRange ? ['start', 'end'] : ['start'];
    $rootAttributes = $attributes->filter(
        fn ($value, $key) => ! str_starts_with($key, 'wire:model') && $key !== 'name',
    );
@endphp

<flux:with-field
    :attributes="$rootAttributes"
    :name="$model"
    :label="$label"
    :description="$description"
    :description:trailing="$descriptionTrailing"
    :badge="$badge"
>
    <div
        x-data="uiDatetimePicker(@js($config), typeof $wire === 'undefined' ? null : $wire)"
        x-init="init($el)"
        wire:ignore
        data-ui-datetime-picker
        data-mode="{{ $mode }}"
        @if ($disabled) data-disabled @endif
        @if ($invalid) data-invalid @endif
        class="relative"
    >
        @if ($formInputId !== null)
            <input
                type="hidden"
                id="{{ $formInputId }}"
                name="{{ $name }}"
                value="{{ is_string($normalizedValue) ? $normalizedValue : '' }}"
            />
        @elseif ($formStartId !== null)
            <input
                type="hidden"
                id="{{ $formStartId }}"
                name="{{ $name }}[start]"
                value="{{ $normalizedValue['start'] ?? '' }}"
            />
            <input
                type="hidden"
                id="{{ $formEndId }}"
                name="{{ $name }}[end]"
                value="{{ $normalizedValue['end'] ?? '' }}"
            />
        @endif

        <div class="relative">
            <flux:input
                id="{{ $triggerId }}"
                readonly
                icon="calendar-days"
                :placeholder="$placeholder ?? ($isRange ? $labels['placeholderRange'] : $labels['placeholder'])"
                :size="$size"
                :invalid="$invalid"
                :disabled="$disabled"
                class:input="{{ $clearable ? 'pe-16' : 'pe-10' }} [@media(hover:none)]:min-h-11"
                x-bind:value="display"
                x-on:click="onTriggerClick()"
                x-on:focus="onTriggerFocus()"
                x-on:keydown="onTriggerKeydown($event)"
                x-bind:aria-expanded="open"
                aria-controls="{{ $popoverId }}"
                aria-haspopup="dialog"
                aria-label="{{ $accessibleLabel }}"
                aria-autocomplete="none"
                autocomplete="off"
                role="combobox"
                data-ui-trigger
            />

            <div class="pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1 text-zinc-400">
                @if ($clearable)
                    <button
                        type="button"
                        x-show="display !== '' && ! disabled"
                        x-cloak
                        x-on:click.stop="clear()"
                        @disabled($disabled)
                        aria-label="{{ $labels['clear'] }}"
                        class="pointer-events-auto rounded-sm p-1 hover:text-zinc-800 disabled:pointer-events-none disabled:opacity-50 dark:hover:text-zinc-200 [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                    >
                        <flux:icon name="x-mark" variant="micro" class="size-4" />
                    </button>
                @endif

                <flux:icon name="chevron-down" variant="micro" class="size-4" />
            </div>
        </div>

        <div
            id="{{ $popoverId }}"
            popover="manual"
            data-ui-popover
            role="dialog"
            aria-modal="false"
            aria-label="{{ $accessibleLabel }}"
            x-on:keydown="onPopoverKeydown($event)"
            x-on:change="onCalendarChange($event)"
            class="z-50 max-h-[calc(100dvh-1rem)] overflow-y-auto rounded-xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-600 dark:bg-zinc-800"
        >
            <div class="flex flex-col gap-4 lg:flex-row">
                <div class="min-w-0 flex-1">
                    <x-ui.calendar
                        draft
                        :mode="$mode"
                        :value="$calendarValue"
                        :min="$minDate"
                        :max="$maxDate"
                        :unavailable="$unavailableDates"
                        :months="$resolvedMonths"
                        :min-range="$minRange"
                        :max-range="$maxRange"
                        :open-to="$openToDate"
                        :start-day="$resolvedStartDay"
                        :fixed-weeks="$fixedWeeks"
                        :with-today="$withToday"
                        :locale="$resolvedLocale"
                    />
                </div>

                <div class="flex shrink-0 flex-col gap-3 lg:w-40">
                    @foreach ($endpoints as $endpoint)
                        <div data-ui-time-columns data-endpoint="{{ $endpoint }}">
                            @if ($isRange)
                                <div class="px-1 pb-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                    {{ $labels[$endpoint] }}
                                </div>
                            @endif

                            <div class="flex gap-1">
                                <div class="w-14">
                                    <div
                                        data-ui-{{ $endpoint }}-hour-label
                                        class="px-1 py-1 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400"
                                    >
                                        {{ $labels['hour'] }}
                                    </div>

                                    <div
                                        x-bind:id="timeListId('{{ $endpoint }}', 'hour')"
                                        data-column="hour"
                                        role="listbox"
                                        tabindex="0"
                                        aria-label="{{ $isRange ? $labels[$endpoint].' '.$labels['hour'] : $labels['hour'] }}"
                                        x-bind:aria-activedescendant="activeTimeId('{{ $endpoint }}', 'hour')"
                                        x-on:keydown="onTimeKeydown($event, '{{ $endpoint }}', 'hour')"
                                        class="max-h-40 overflow-y-auto scroll-py-2"
                                    >
                                        <template x-for="option in {{ $endpoint }}.hours" :key="option.id">
                                            <div
                                                role="option"
                                                :id="option.id"
                                                :data-hour="option.value"
                                                :aria-selected="option.selected ? 'true' : 'false'"
                                                :aria-disabled="option.disabled || null"
                                                x-on:click="selectHour('{{ $endpoint }}', option.value)"
                                                :class="option.disabled
                                                    ? 'cursor-default text-zinc-300 dark:text-zinc-600'
                                                    : option.selected
                                                        ? 'cursor-pointer bg-accent text-accent-foreground'
                                                        : 'cursor-pointer text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                                                class="flex h-8 items-center justify-center rounded-md text-sm tabular-nums transition-colors [@media(hover:none)]:min-h-11"
                                                x-text="option.label"
                                            ></div>
                                        </template>
                                    </div>
                                </div>

                                <div class="w-14">
                                    <div
                                        data-ui-{{ $endpoint }}-minute-label
                                        class="px-1 py-1 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400"
                                    >
                                        {{ $labels['minute'] }}
                                    </div>

                                    <div
                                        x-bind:id="timeListId('{{ $endpoint }}', 'minute')"
                                        data-column="minute"
                                        role="listbox"
                                        tabindex="0"
                                        aria-label="{{ $isRange ? $labels[$endpoint].' '.$labels['minute'] : $labels['minute'] }}"
                                        x-bind:aria-activedescendant="activeTimeId('{{ $endpoint }}', 'minute')"
                                        x-on:keydown="onTimeKeydown($event, '{{ $endpoint }}', 'minute')"
                                        class="max-h-40 overflow-y-auto scroll-py-2"
                                    >
                                        <template x-for="option in {{ $endpoint }}.minutes" :key="option.id">
                                            <div
                                                role="option"
                                                :id="option.id"
                                                :data-minute="option.value"
                                                :aria-selected="option.selected ? 'true' : 'false'"
                                                :aria-disabled="option.disabled || null"
                                                x-on:click="selectMinute('{{ $endpoint }}', option.value)"
                                                :class="option.disabled
                                                    ? 'cursor-default text-zinc-300 dark:text-zinc-600'
                                                    : option.selected
                                                        ? 'cursor-pointer bg-accent text-accent-foreground'
                                                        : 'cursor-pointer text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                                                class="flex h-8 items-center justify-center rounded-md text-sm tabular-nums transition-colors [@media(hover:none)]:min-h-11"
                                                x-text="option.label"
                                            ></div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="hour12" class="flex flex-col gap-1 pt-6">
                                    <button
                                        type="button"
                                        x-on:click="setMeridiem('{{ $endpoint }}', 'am')"
                                        x-bind:aria-pressed="{{ $endpoint }}.meridiem === 'am' ? 'true' : 'false'"
                                        x-bind:class="{{ $endpoint }}.meridiem === 'am' ? 'bg-accent text-accent-foreground' : 'text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                                        class="flex h-8 min-w-10 items-center justify-center rounded-md px-1 text-xs font-medium transition-colors [@media(hover:none)]:min-h-11"
                                        x-text="labels.am"
                                    ></button>

                                    <button
                                        type="button"
                                        x-on:click="setMeridiem('{{ $endpoint }}', 'pm')"
                                        x-bind:aria-pressed="{{ $endpoint }}.meridiem === 'pm' ? 'true' : 'false'"
                                        x-bind:class="{{ $endpoint }}.meridiem === 'pm' ? 'bg-accent text-accent-foreground' : 'text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                                        class="flex h-8 min-w-10 items-center justify-center rounded-md px-1 text-xs font-medium transition-colors [@media(hover:none)]:min-h-11"
                                        x-text="labels.pm"
                                    ></button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div x-show="rangeError !== ''" role="status" class="mt-2 text-sm text-red-600 dark:text-red-400" x-text="rangeError"></div>

            @if ($withConfirmation || $isRange)
                <div class="mt-3 flex justify-end gap-2 border-t border-zinc-200 pt-3 dark:border-white/10">
                    <flux:button
                        type="button"
                        variant="ghost"
                        size="sm"
                        x-on:click="cancel()"
                        class="[@media(hover:none)]:min-h-11"
                    >{{ $labels['cancel'] }}</flux:button>

                    <flux:button
                        type="button"
                        variant="primary"
                        size="sm"
                        x-bind:disabled="!canConfirm()"
                        x-on:click="confirm()"
                        class="[@media(hover:none)]:min-h-11"
                    >{{ $isRange ? $labels['selectRange'] : $labels['select'] }}</flux:button>
                </div>
            @endif
        </div>
    </div>
</flux:with-field>
