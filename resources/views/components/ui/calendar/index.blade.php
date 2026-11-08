@props([
    'mode' => 'single',
    'value' => null,
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'badge' => null,
    'min' => null,
    'max' => null,
    'unavailable' => null,
    'size' => 'base',
    'startDay' => null,
    'months' => null,
    'fixedWeeks' => false,
    'minRange' => null,
    'maxRange' => null,
    'openTo' => null,
    'navigation' => true,
    'static' => false,
    'withToday' => false,
    'locale' => null,
    'invalid' => null,
    'draft' => false,
])

@php
    $wireModel = $attributes->wire('model');
    $model = $wireModel->value();
    $model = is_string($model) && $model !== '' ? $model : null;
    $live = $wireModel->hasModifier('live');
    $name = $attributes->get('name');
    $invalid ??= $model !== null && $errors->has($model);

    $mode = in_array($mode, ['single', 'multiple', 'range'], true) ? $mode : 'single';
    $resolvedMonths = min(2, max(1, (int) ($months ?? ($mode === 'range' ? 2 : 1))));
    $resolvedLocale = $locale ?? app()->getLocale();
    $resolvedStartDay = $startDay === null ? null : min(6, max(0, (int) $startDay));
    $resolvedMin = $min === 'today' ? 'today' : \App\View\Value::date($min);
    $resolvedMax = $max === 'today' ? 'today' : \App\View\Value::date($max);
    $resolvedOpenTo = \App\View\Value::date($openTo);
    $normalizedValue = \App\View\Value::calendar($value, $mode);
    $unavailableKeys = \App\View\Value::dates($unavailable);
    $rootId = 'ui-calendar-'.\Illuminate\Support\Str::random(12);
    $isPlain = $model === null && $name !== null;
    $formInputId = $isPlain && $mode === 'single' ? $rootId.'-input' : null;

    $cellClass = match ($size) {
        'sm' => 'size-9 text-sm',
        default => 'size-10 text-sm',
    };

    $labels = [
        'today' => __('ui.calendar.today'),
        'previousMonth' => __('ui.calendar.previous-month'),
        'nextMonth' => __('ui.calendar.next-month'),
        'selected' => __('ui.calendar.selected'),
        'unavailable' => __('ui.calendar.unavailable'),
        'rangeTooShort' => __('ui.calendar.range-too-short'),
        'rangeTooLong' => __('ui.calendar.range-too-long'),
        'rangeUnavailable' => __('ui.calendar.range-unavailable'),
    ];

    $config = [
        'mode' => $mode,
        'draft' => (bool) $draft,
        'value' => $normalizedValue,
        'model' => $model,
        'live' => $live,
        'locale' => $resolvedLocale,
        'startDay' => $resolvedStartDay,
        'months' => $resolvedMonths,
        'fixedWeeks' => (bool) $fixedWeeks,
        'min' => $resolvedMin,
        'max' => $resolvedMax,
        'unavailable' => $unavailableKeys,
        'minRange' => $minRange === null ? null : (int) $minRange,
        'maxRange' => $maxRange === null ? null : (int) $maxRange,
        'openTo' => $resolvedOpenTo,
        'navigation' => (bool) $navigation,
        'static' => (bool) $static,
        'withToday' => (bool) $withToday,
        'rootId' => $rootId,
        'cellClass' => $cellClass,
        'formInputId' => $formInputId,
        'labels' => $labels,
    ];

    $rootAttributes = $attributes->filter(fn ($value, $key) => ! str_starts_with($key, 'wire:model'));
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
        x-data="uiCalendar(@js($config), typeof $wire === 'undefined' ? null : $wire)"
        x-init="init($el)"
        wire:ignore
        data-ui-calendar
        data-mode="{{ $mode }}"
        @if ($draft) data-draft @endif
        :data-start-day="startDay"
        @if ($invalid) data-invalid style="box-shadow: 0 0 0 2px #fb2c36" @endif
        class="w-full space-y-2 data-invalid:rounded-lg data-invalid:p-2"
    >
        @if ($isPlain && $mode === 'single')
            <input
                type="hidden"
                id="{{ $formInputId }}"
                name="{{ $name }}"
                value="{{ is_string($normalizedValue) ? $normalizedValue : '' }}"
            />
        @elseif ($isPlain && $mode === 'multiple')
            <template x-for="value in selectedValues" :key="value">
                <input type="hidden" name="{{ $name }}[]" :value="value" />
            </template>
        @elseif ($isPlain && $mode === 'range')
            <input type="hidden" name="{{ $name }}[start]" :value="rangeStart || ''" />
            <input type="hidden" name="{{ $name }}[end]" :value="rangeEnd || ''" />
        @endif

        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1" x-show="navigation">
                <flux:button
                    type="button"
                    variant="ghost"
                    size="sm"
                    icon="chevron-left"
                    x-on:click="previousMonth()"
                    aria-label="{{ $labels['previousMonth'] }}"
                    class="[@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                />

                <flux:button
                    type="button"
                    variant="ghost"
                    size="sm"
                    icon="chevron-right"
                    x-on:click="nextMonth()"
                    aria-label="{{ $labels['nextMonth'] }}"
                    class="[@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                />
            </div>

            <div class="text-sm font-medium text-zinc-800 dark:text-white" x-text="heading"></div>

            <flux:button
                type="button"
                variant="ghost"
                size="sm"
                x-show="withToday"
                x-on:click="todayAction()"
                x-bind:aria-label="labels.today"
                class="[@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
            >{{ $labels['today'] }}</flux:button>
        </div>

        <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="announcement"></div>

        <div
            x-show="monthsData.length === 0"
            class="h-64 animate-pulse rounded-lg bg-zinc-100 dark:bg-white/5"
        ></div>

        <div
            x-show="monthsData.length > 0"
            class="flex gap-4"
            :class="monthsData.length > 1 ? 'flex-col sm:flex-row' : ''"
        >
            <template x-for="month in monthsData" :key="month.key">
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="text-sm font-medium text-zinc-800 dark:text-white" x-text="month.label"></div>

                    <table
                        role="grid"
                        :aria-label="month.label"
                        @if ($invalid) aria-invalid="true" @endif
                        class="w-full border-separate border-spacing-y-1"
                    >
                        <thead>
                            <tr>
                                <template x-for="weekday in month.weekdays" :key="weekday">
                                    <th
                                        scope="col"
                                        class="pb-1 text-xs font-medium text-zinc-500 dark:text-zinc-400"
                                        x-text="weekday"
                                    ></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="week in month.weeks" :key="week[0].key">
                                <tr>
                                    <template x-for="cell in week" :key="cell.id">
                                        <td
                                            role="gridcell"
                                            :data-date="cell.key"
                                            :data-outside="cell.outside || null"
                                            :data-today="cell.today || null"
                                            :data-selected="cell.selected || null"
                                            :data-in-range="cell.inRange || null"
                                            :data-start="cell.rangeStart || null"
                                            :data-end="cell.rangeEnd || null"
                                            :data-unavailable="cell.unavailable || null"
                                            :data-active="cell.active || null"
                                            :aria-selected="cell.selected ? 'true' : 'false'"
                                            :aria-disabled="cell.disabled || static ? 'true' : null"
                                            class="p-0 data-in-range:bg-zinc-100 data-start:rounded-s-lg data-end:rounded-e-lg data-unavailable:line-through dark:data-in-range:bg-white/10"
                                        >
                                            <button
                                                type="button"
                                                :id="cell.id"
                                                :disabled="cell.disabled || static"
                                                :tabindex="cell.tabIndex"
                                                :aria-label="cell.label"
                                                :aria-current="cell.today ? 'date' : null"
                                                x-on:click="select(cell.key)"
                                                x-on:focus="onFocus(cell.key)"
                                                x-on:mouseenter="onHover(cell.key)"
                                                x-on:mouseleave="clearPreview()"
                                                x-on:keydown="onKeydown($event, cell.key)"
                                                :class="cellClass"
                                                class="mx-auto flex items-center justify-center rounded-lg font-medium text-zinc-800 transition-colors hover:bg-zinc-800/5 focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-accent-foreground focus:outline-hidden disabled:pointer-events-none disabled:text-zinc-400 dark:text-white dark:hover:bg-white/5 [td[data-outside]_&]:text-zinc-400/60 [td[data-selected]_&]:bg-accent [td[data-selected]_&]:text-accent-foreground [td[data-today]_&]:ring-1 [td[data-today]_&]:ring-accent/40 [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                                                x-text="cell.day"
                                            ></button>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <div
            x-show="rangeError !== ''"
            role="status"
            class="text-sm text-red-600 dark:text-red-400"
            x-text="rangeError"
        ></div>
    </div>
</flux:with-field>
