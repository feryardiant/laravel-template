@props([
    'mode' => 'single',
    'value' => null,
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'badge' => null,
    'placeholder' => null,
    'size' => null,
    'min' => null,
    'max' => null,
    'unavailable' => null,
    'openTo' => null,
    'minRange' => null,
    'maxRange' => null,
    'months' => null,
    'startDay' => null,
    'fixedWeeks' => false,
    'withToday' => false,
    'withConfirmation' => false,
    'withPresets' => false,
    'presets' => 'today yesterday thisWeek last7Days thisMonth yearToDate allTime',
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

    $mode = in_array($mode, ['single', 'range'], true) ? $mode : 'single';
    $isRange = $mode === 'range';
    $resolvedMonths = min(2, max(1, (int) ($months ?? ($isRange ? 2 : 1))));
    $resolvedLocale = $locale ?? app()->getLocale();
    $resolvedStartDay = $startDay === null ? null : min(6, max(0, (int) $startDay));
    $resolvedMin = $min === 'today' ? 'today' : \App\View\Value::date($min);
    $resolvedMax = $max === 'today' ? 'today' : \App\View\Value::date($max);
    $resolvedOpenTo = \App\View\Value::date($openTo);
    $normalizedValue = \App\View\Value::calendar($value, $mode);
    $unavailableKeys = \App\View\Value::dates($unavailable);

    $allowedPresets = ['today', 'yesterday', 'thisWeek', 'lastWeek', 'last7Days', 'last14Days', 'last30Days', 'thisMonth', 'lastMonth', 'thisYear', 'lastYear', 'yearToDate', 'allTime'];
    $presetKeys = is_string($presets) ? preg_split('/\s+/', trim($presets)) : [];
    $presetKeys = array_values(array_intersect($allowedPresets, array_filter($presetKeys)));

    if ($resolvedMin === null) {
        $presetKeys = array_values(array_diff($presetKeys, ['allTime']));
    }

    $key = \Illuminate\Support\Str::random(12);
    $rootId = 'ui-date-picker-'.$key;
    $triggerId = $rootId.'-trigger';
    $popoverId = $rootId.'-popover';
    $isPlain = $model === null && $name !== null;
    $formInputId = $isPlain && ! $isRange ? $rootId.'-input' : null;
    $formStartId = $isPlain && $isRange ? $rootId.'-start' : null;
    $formEndId = $isPlain && $isRange ? $rootId.'-end' : null;

    $accessibleLabel = $label ?? $attributes->get('aria-label') ?? $placeholder ?? __('ui.date-picker.label');

    $labels = [
        'placeholder' => __('ui.date-picker.placeholder'),
        'placeholderRange' => __('ui.date-picker.placeholder-range'),
        'clear' => __('ui.date-picker.clear'),
        'cancel' => __('ui.date-picker.cancel'),
        'select' => __('ui.date-picker.select'),
        'selectRange' => __('ui.date-picker.select-range'),
        'presets' => __('ui.date-picker.presets'),
        'presetLabels' => [
            'today' => __('ui.date-picker.preset.today'),
            'yesterday' => __('ui.date-picker.preset.yesterday'),
            'thisWeek' => __('ui.date-picker.preset.this-week'),
            'lastWeek' => __('ui.date-picker.preset.last-week'),
            'last7Days' => __('ui.date-picker.preset.last-7-days'),
            'last14Days' => __('ui.date-picker.preset.last-14-days'),
            'last30Days' => __('ui.date-picker.preset.last-30-days'),
            'thisMonth' => __('ui.date-picker.preset.this-month'),
            'lastMonth' => __('ui.date-picker.preset.last-month'),
            'thisYear' => __('ui.date-picker.preset.this-year'),
            'lastYear' => __('ui.date-picker.preset.last-year'),
            'yearToDate' => __('ui.date-picker.preset.year-to-date'),
            'allTime' => __('ui.date-picker.preset.all-time'),
        ],
    ];

    $config = [
        'mode' => $mode,
        'value' => $normalizedValue,
        'model' => $model,
        'live' => $live,
        'locale' => $resolvedLocale,
        'startDay' => $resolvedStartDay,
        'min' => $resolvedMin,
        'max' => $resolvedMax,
        'unavailable' => $unavailableKeys,
        'minRange' => $minRange === null ? null : (int) $minRange,
        'maxRange' => $maxRange === null ? null : (int) $maxRange,
        'withConfirmation' => (bool) $withConfirmation,
        'withPresets' => (bool) $withPresets,
        'presetKeys' => $presetKeys,
        'placeholder' => $placeholder,
        'clearable' => (bool) $clearable,
        'disabled' => (bool) $disabled,
        'triggerId' => $triggerId,
        'popoverId' => $popoverId,
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
        x-data="uiDatePicker(@js($config), typeof $wire === 'undefined' ? null : $wire)"
        x-init="init($el)"
        wire:ignore
        data-ui-date-picker
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
                        x-show="start !== null && ! disabled"
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

        <x-ui.popover
            :id="$popoverId"
            role="dialog"
            :label="$accessibleLabel"
            x-on:keydown="onPopoverKeydown($event)"
            x-on:change="onCalendarChange($event)"
            class="max-h-[calc(100dvh-1rem)] overflow-y-auto rounded-xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-600 dark:bg-zinc-800"
        >
            <div class="flex flex-col gap-3 sm:flex-row">
                @if ($withPresets && count($presetKeys) > 0)
                    <div
                        x-show="presets.length > 0"
                        x-cloak
                        class="flex shrink-0 gap-1 overflow-x-auto sm:w-36 sm:flex-col"
                        role="group"
                        aria-label="{{ $labels['presets'] }}"
                    >
                        <template x-for="preset in presets" :key="preset.key">
                            <button
                                type="button"
                                x-on:click="selectPreset(preset.key)"
                                x-bind:aria-pressed="presetActive(preset.key) ? 'true' : 'false'"
                                x-bind:title="preset.label"
                                x-bind:class="presetActive(preset.key)
                                    ? 'bg-zinc-800/5 font-medium text-zinc-800 dark:bg-white/10 dark:text-white'
                                    : 'text-zinc-600 hover:bg-zinc-800/5 dark:text-zinc-300 dark:hover:bg-white/10'"
                                class="truncate rounded-md px-2 py-1.5 text-start text-sm transition-colors [@media(hover:none)]:min-h-11"
                                x-text="preset.label"
                            ></button>
                        </template>
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <x-ui.calendar
                        draft
                        :value="$normalizedValue"
                        :mode="$mode"
                        :min="$resolvedMin"
                        :max="$resolvedMax"
                        :unavailable="$unavailableKeys"
                        :months="$resolvedMonths"
                        :min-range="$minRange"
                        :max-range="$maxRange"
                        :open-to="$resolvedOpenTo"
                        :start-day="$resolvedStartDay"
                        :fixed-weeks="$fixedWeeks"
                        :with-today="$withToday"
                        :locale="$resolvedLocale"
                        :size="$size ?? 'base'"
                    />
                </div>
            </div>

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
