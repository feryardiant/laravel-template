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
    $resolvedMonths = min(2, max(1, (int) ($months ?? 1)));
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
        'cancel' => __('ui.date-picker.cancel'),
        'select' => __('ui.date-picker.select'),
        'selectRange' => __('ui.date-picker.select-range'),
        'rangeInvalid' => __('ui.datetime-picker.range-invalid'),
        'addStart' => __('ui.time-picker.add-start'),
        'addEnd' => __('ui.time-picker.add-end'),
        'removeStart' => __('ui.datetime-picker.remove-start'),
        'removeEnd' => __('ui.datetime-picker.remove-end'),
    ];

    $config = [
        'mode' => $mode,
        'value' => $normalizedValue,
        'model' => $model,
        'live' => $live,
        'locale' => $locale,
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

    $optionHeight = $isRange ? 'h-7' : 'h-8';
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
                @disabled($disabled)
            />
        @elseif ($formStartId !== null)
            <input
                type="hidden"
                id="{{ $formStartId }}"
                name="{{ $name }}[start]"
                value="{{ $normalizedValue['start'] ?? '' }}"
                @disabled($disabled)
            />
            <input
                type="hidden"
                id="{{ $formEndId }}"
                name="{{ $name }}[end]"
                value="{{ $normalizedValue['end'] ?? '' }}"
                @disabled($disabled)
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
                aria-controls="{{ $popoverId }}"
                aria-haspopup="dialog"
                aria-label="{{ $accessibleLabel }}"
                aria-autocomplete="none"
                autocomplete="off"
                role="combobox"
                data-ui-trigger
            />

            <div class="pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1">
                @if ($clearable)
                    <flux:button
                        type="button"
                        variant="ghost"
                        icon="x-mark"
                        size="xs"
                        x-cloak
                        x-show="display !== '' && ! disabled"
                        x-on:click.stop="clear()"
                        :disabled="$disabled"
                        aria-label="{{ $labels['clear'] }}"
                        class="pointer-events-auto"
                    />
                @endif

                <flux:icon name="chevron-down" variant="micro" class="size-4" />
            </div>
        </div>

        <x-ui.popover
            :id="$popoverId"
            role="dialog"
            :label="$accessibleLabel"
            x-on:keydown="onPopoverKeydown($event)"
            x-on:change="onCalendarChange($event)"
            class="max-h-[calc(100dvh-1rem)] overflow-y-auto p-3"
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
                        :locale="$locale"
                    />
                </div>

                <div
                    data-ui-time-panel
                    @class([
                        'flex shrink-0 flex-col',
                        'min-w-36' => $isRange
                    ])
                >
                    <div
                        data-ui-time-columns
                        x-bind:data-endpoint="activeEndpoint()"
                        class="flex flex-col lg:min-h-0 lg:flex-1"
                    >
                        <x-ui.time-picker.panel
                            hours="draft.hours"
                            minutes="draft.minutes"
                            active-hour="activeTimeId('hour')"
                            active-minute="activeTimeId('minute')"
                            hour-keydown="onTimeKeydown($event, 'hour')"
                            minute-keydown="onTimeKeydown($event, 'minute')"
                            select-hour="selectHour(option.value)"
                            select-minute="selectMinute(option.value)"
                            hour-id="timeListId('hour')"
                            minute-id="timeListId('minute')"
                            bound-ids
                            is-am="draft.meridiem === 'am'"
                            is-pm="draft.meridiem === 'pm'"
                            on-select-am="setMeridiem('am')"
                            on-select-pm="setMeridiem('pm')"
                            class="min-h-0 flex-1"
                            class:column="flex flex-1 flex-col min-w-11"
                            class:option="{{ $optionHeight }}"
                            class:list="{{ $isRange
                                ? 'max-h-44 overflow-y-auto scroll-py-2'
                                : 'max-h-44 overflow-y-auto scroll-py-2 lg:max-h-none lg:min-h-0 lg:flex-1' }}"
                            class:meridiem-wrapper="flex flex-col gap-1 pt-6"
                            class:meridiem="flex h-8 min-w-10 items-center justify-center rounded-md px-1 text-xs font-medium transition-colors [@media(hover:none)]:min-h-11"
                        />
                    </div>

                    @if ($isRange)
                        <div class="flex flex-col gap-2 border-t border-zinc-200 pt-2 dark:border-white/10">
                            <div data-ui-selected class="flex flex-wrap gap-1">
                                <div
                                    x-show="rangeStart !== null"
                                    data-time="start"
                                    x-on:click.stop="removeAdded('start')"
                                    class="inline-flex max-w-full cursor-pointer items-center gap-1 rounded-md bg-zinc-400/15 p-1 pl-2 text-sm leading-4 text-zinc-700 select-none dark:bg-zinc-400/40 dark:text-zinc-200"
                                >
                                    <span class="min-w-0 truncate" x-text="formatTimeValue(rangeStart)"></span>

                                    <flux:button
                                        type="button"
                                        variant="ghost"
                                        size="xs"
                                        icon="x-mark"
                                        x-on:click.stop="removeAdded('start')"
                                        aria-label="{{ $labels['removeStart'] }}"
                                        class="size-4"
                                    />
                                </div>

                                <div
                                    x-show="rangeEnd !== null"
                                    data-time="end"
                                    x-on:click.stop="removeAdded('end')"
                                    class="inline-flex max-w-full cursor-pointer items-center gap-1 rounded-md bg-zinc-400/15 p-1 pl-2 text-sm leading-4 text-zinc-700 select-none dark:bg-zinc-400/40 dark:text-zinc-200"
                                >
                                    <span class="min-w-0 truncate" x-text="formatTimeValue(rangeEnd)"></span>

                                    <flux:button
                                        type="button"
                                        variant="ghost"
                                        size="xs"
                                        icon="x-mark"
                                        x-on:click.stop="removeAdded('end')"
                                        aria-label="{{ $labels['removeEnd'] }}"
                                        class="size-4"
                                    />
                                </div>
                            </div>

                            <button
                                type="button"
                                data-ui-add
                                x-show="canAddMore()"
                                x-bind:disabled="!canAddTime()"
                                x-on:click="addTime()"
                                class="inline-flex h-8 w-full items-center justify-center rounded-md bg-accent px-3 text-sm font-medium text-accent-foreground transition-colors disabled:cursor-default disabled:opacity-50 [@media(hover:none)]:min-h-11"
                                x-text="addLabel()"
                            ></button>
                        </div>
                    @endif
                </div>
            </div>

            <div x-show="rangeError !== ''" role="status" data-ui-range-error class="mt-2 text-sm text-red-600 select-text dark:text-red-400" x-text="rangeError"></div>

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
        </x-ui.popover>
    </div>
</flux:with-field>
