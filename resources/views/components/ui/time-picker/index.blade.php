@props([
    'mode' => 'single',
    'value' => null,
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'badge' => null,
    'placeholder' => null,
    'size' => null,
    'timeFormat' => 'auto',
    'interval' => 30,
    'min' => null,
    'max' => null,
    'unavailable' => null,
    'openTo' => null,
    'clearable' => false,
    'disabled' => false,
    'invalid' => null,
    'locale' => null,
])

@php
    $wireModel = $attributes->wire('model');
    $model = $wireModel->value();
    $model = is_string($model) && $model !== '' ? $model : null;
    $live = $wireModel->hasModifier('live');
    $name = $attributes->get('name');
    $invalid ??= $model !== null && $errors->has($model);

    $mode = in_array($mode, ['single', 'multiple', 'range'], true) ? $mode : 'single';
    $isRange = $mode === 'range';
    $isMultiple = $mode === 'multiple';
    $format = in_array($timeFormat, ['auto', '12-hour', '24-hour'], true) ? $timeFormat : 'auto';
    $resolvedInterval = min(60, max(1, (int) $interval));
    $resolvedMin = $min === 'now' ? 'now' : \App\View\Value::time($min);
    $resolvedMax = $max === 'now' ? 'now' : \App\View\Value::time($max);
    $resolvedOpenTo = $openTo === 'now' ? 'now' : \App\View\Value::time($openTo);
    $unavailableRanges = \App\View\Value::timeRanges($unavailable);
    $normalizedValue = match ($mode) {
        'multiple' => \App\View\Value::times($value),
        'range' => \App\View\Value::timeRange($value),
        default => \App\View\Value::time($value),
    };

    $key = \Illuminate\Support\Str::random(12);
    $triggerId = 'ui-time-picker-'.$key.'-trigger';
    $popoverId = 'ui-time-picker-'.$key.'-popover';
    $hoursId = 'ui-time-picker-'.$key.'-hours';
    $minutesId = 'ui-time-picker-'.$key.'-minutes';
    $selectedId = 'ui-time-picker-'.$key.'-selected';
    $isPlain = $model === null && $name !== null;
    $formInputId = $isPlain && $mode === 'single' ? 'ui-time-picker-'.$key.'-input' : null;
    $formStartId = $isPlain && $isRange ? 'ui-time-picker-'.$key.'-start' : null;
    $formEndId = $isPlain && $isRange ? 'ui-time-picker-'.$key.'-end' : null;

    $accessibleLabel = $label ?? $attributes->get('aria-label') ?? $placeholder ?? __('ui.time-picker.label');

    $labels = [
        'clear' => __('ui.time-picker.clear'),
        'add' => __('ui.time-picker.add'),
        'selected' => __('ui.time-picker.selected'),
        'selectedCount' => __('ui.time-picker.selected-count'),
        'placeholder' => __('ui.time-picker.placeholder'),
        'placeholderMultiple' => __('ui.time-picker.placeholder-multiple'),
        'placeholderRange' => __('ui.time-picker.placeholder-range'),
        'addStart' => __('ui.time-picker.add-start'),
        'addEnd' => __('ui.time-picker.add-end'),
    ];

    $config = [
        'mode' => $mode,
        'value' => $normalizedValue,
        'model' => $model,
        'live' => $live,
        'locale' => $locale,
        'format' => $format,
        'interval' => $resolvedInterval,
        'min' => $resolvedMin,
        'max' => $resolvedMax,
        'unavailable' => $unavailableRanges,
        'openTo' => $resolvedOpenTo,
        'placeholder' => $placeholder,
        'clearable' => (bool) $clearable,
        'disabled' => (bool) $disabled,
        'triggerId' => $triggerId,
        'popoverId' => $popoverId,
        'hoursId' => $hoursId,
        'minutesId' => $minutesId,
        'selectedId' => $selectedId,
        'formInputId' => $formInputId,
        'formStartId' => $formStartId,
        'formEndId' => $formEndId,
        'labels' => $labels,
    ];

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
        x-data="uiTimePicker(@js($config), typeof $wire === 'undefined' ? null : $wire)"
        x-init="init($el)"
        wire:ignore
        data-ui-time-picker
        @if ($isMultiple) data-multiple @endif
        @if ($isRange) data-range @endif
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
        @elseif ($isPlain && $isMultiple)
            <template x-for="selected in selectedValues" :key="selected">
                <input type="hidden" name="{{ $name }}[]" :value="selected" />
            </template>
        @elseif ($formStartId !== null)
            <input type="hidden" id="{{ $formStartId }}" name="{{ $name }}[start]" :value="rangeStart || ''" />
            <input type="hidden" id="{{ $formEndId }}" name="{{ $name }}[end]" :value="rangeEnd || ''" />
        @endif

        <div class="relative">
            <flux:input
                id="{{ $triggerId }}"
                readonly
                icon="clock"
                :placeholder="$placeholder ?? ($isRange ? $labels['placeholderRange'] : ($isMultiple ? $labels['placeholderMultiple'] : $labels['placeholder']))"
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

            <div class="pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1 text-zinc-400">
                @if ($clearable)
                    <flux:button
                        type="button"
                        variant="ghost"
                        icon="x-mark"
                        size="xs"
                        x-cloak
                        x-show="hasValue() && ! disabled"
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
            class="p-2"
        >
            <x-ui.time-picker.panel
                hours="hours"
                minutes="minutes"
                active-hour="activeHourId()"
                active-minute="activeMinuteId()"
                hour-keydown="onHourKeydown($event)"
                minute-keydown="onMinuteKeydown($event)"
                select-hour="selectHour(option.value)"
                select-minute="selectMinute(option.value)"
                :hour-id="$hoursId"
                :minute-id="$minutesId"
                is-am="meridiem === 'am'"
                is-pm="meridiem === 'pm'"
                on-select-am="setMeridiem('am')"
                on-select-pm="setMeridiem('pm')"
            />

            <div x-show="multiple" x-cloak class="mt-2 space-y-2 border-t border-zinc-200 pt-2 dark:border-white/10">
                <div
                    id="{{ $selectedId }}"
                    data-ui-selected
                    role="listbox"
                    tabindex="0"
                    aria-multiselectable="true"
                    x-bind:aria-label="labels.selected"
                    x-bind:aria-activedescendant="activeSelectedId()"
                    x-on:keydown="onSelectedKeydown($event)"
                    class="max-h-32 overflow-y-auto scroll-py-2"
                >
                    <template x-for="selected in selectedValues" :key="selected">
                        <div
                            role="option"
                            :id="selectedOptionId(selected)"
                            :data-time="selected"
                            aria-selected="true"
                            x-on:click="remove(selected)"
                            class="flex cursor-pointer items-center justify-between rounded-md px-2 py-1 text-sm tabular-nums text-zinc-700 hover:bg-zinc-800/5 dark:text-zinc-200 dark:hover:bg-white/10 [@media(hover:none)]:min-h-11"
                        >
                            <span x-text="formatSelected(selected)"></span>

                            <flux:icon name="x-mark" variant="micro" class="size-4 text-zinc-400" />
                        </div>
                    </template>
                </div>

                <button
                    type="button"
                    data-ui-add
                    x-show="canAddMore()"
                    x-bind:disabled="! canAdd()"
                    x-on:click="add()"
                    class="inline-flex h-8 w-full items-center justify-center rounded-md bg-accent px-3 text-sm font-medium text-accent-foreground transition-colors disabled:cursor-default disabled:opacity-50 [@media(hover:none)]:min-h-11"
                    x-text="addLabel()"
                ></button>
            </div>
        </x-ui.popover>
    </div>
</flux:with-field>
