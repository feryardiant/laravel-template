@props([
    'variant' => 'listbox',
    'multiple' => false,
    'value' => null,
    'placeholder' => null,
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'badge' => null,
    'size' => null,
    'icon' => null,
    'clearable' => false,
    'empty' => null,
    'selectedSuffix' => null,
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

    $key = \Illuminate\Support\Str::random(12);
    $triggerId = 'ui-select-'.$key.'-trigger';
    $popoverId = 'ui-select-'.$key.'-popover';
    $listboxId = 'ui-select-'.$key.'-listbox';
    $anchorId = $variant === 'pillbox' ? 'ui-select-'.$key.'-anchor' : $triggerId;

    $empty ??= __('ui.select.no-results');
    $selectedSuffix ??= __('ui.select.selected');
    $optionsClass = $attributes->get('options:class');
    $accessibleLabel = $label ?? $attributes->get('aria-label') ?? $placeholder ?? __('ui.common.select');
    $isPlain = $model === null && $name !== null;
    $isMultiple = (bool) $multiple || $variant === 'pillbox';
    $formInputId = $isPlain && ! $isMultiple ? 'ui-select-'.$key.'-input' : null;

    $config = [
        'variant' => $variant,
        'multiple' => (bool) $multiple,
        'value' => $value,
        'model' => $model,
        'live' => $live,
        'disabled' => (bool) $disabled,
        'empty' => $empty,
        'selectedSuffix' => $selectedSuffix,
        'triggerId' => $triggerId,
        'anchorId' => $anchorId,
        'popoverId' => $popoverId,
        'listboxId' => $listboxId,
        'formInputId' => $formInputId,
    ];

    $rootAttributes = $attributes->filter(
        fn ($value, $key) => ! str_starts_with($key, 'wire:model') && $key !== 'options:class',
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
        x-data="uiSelect(@js($config), typeof $wire === 'undefined' ? null : $wire)"
        x-init="init()"
        wire:ignore
        data-ui-select
        data-variant="{{ $variant }}"
        @if ($disabled) data-disabled @endif
        @if ($invalid) data-invalid @endif
        class="relative"
    >
        @if ($formInputId !== null)
            <input
                type="hidden"
                id="{{ $formInputId }}"
                name="{{ $name }}"
                value="{{ is_scalar($value) ? $value : '' }}"
            />
        @endif

        @if ($isPlain && $isMultiple)
            <template x-for="selected in selectedValues" :key="selected">
                <input type="hidden" name="{{ $name }}[]" :value="selected" />
            </template>
        @endif

        @if ($variant === 'pillbox')
            <div
                id="{{ $anchorId }}"
                x-on:click="focusSearch()"
                @class([
                    'flex min-h-10 w-full flex-wrap items-center gap-1 rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-2 py-1.5 text-base shadow-xs focus-within:ring-2 focus-within:ring-accent focus-within:ring-offset-2 focus-within:ring-offset-accent-foreground sm:text-sm dark:border-white/10 dark:bg-white/10 [@media(hover:none)]:min-h-11',
                    'opacity-50' => $disabled,
                    'border-red-500 dark:border-red-500' => $invalid,
                ])
                data-flux-control
                data-flux-group-target
            >
                <template x-for="pill in selectedOptions" :key="pill.value">
                    <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-zinc-400/15 px-2 py-1 text-sm leading-4 text-zinc-700 dark:bg-zinc-400/40 dark:text-zinc-200">
                        <span class="min-w-0 truncate" x-text="pill.selectedLabel"></span>

                        <button
                            type="button"
                            x-on:click.stop="removeValue(pill.value)"
                            x-bind:aria-label="{{ \Illuminate\Support\Js::from(__('ui.select.remove', ['label' => ':label'])) }}.replace(':label', pill.selectedLabel)"
                            @disabled($disabled)
                            class="shrink-0 rounded-sm p-0.5 text-zinc-400 hover:text-zinc-800 disabled:pointer-events-none disabled:opacity-50 dark:hover:text-zinc-200 [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                        >
                            <flux:icon name="x-mark" variant="micro" class="size-4" />
                        </button>
                    </span>
                </template>

                <input
                    id="{{ $triggerId }}"
                    x-model="query"
                    x-on:input="onPillboxInput($event)"
                    x-on:keydown="onPillboxKeydown($event)"
                    x-on:focus="onPillboxFocus()"
                    x-bind:aria-expanded="open"
                    x-bind:aria-activedescendant="activeId"
                    x-bind:placeholder="selectedValues.length === 0 ? @js($placeholder ?? '') : ''"
                    type="text"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-haspopup="listbox"
                    aria-controls="{{ $popoverId }}"
                    @if ($accessibleLabel) aria-label="{{ $accessibleLabel }}" @endif
                    @if ($invalid) aria-invalid="true" @endif
                    autocomplete="off"
                    @disabled($disabled)
                    class="min-w-20 flex-1 border-0 bg-transparent p-1 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 focus:outline-hidden dark:text-zinc-200 dark:placeholder-zinc-400"
                />

                @if ($clearable)
                    <button
                        type="button"
                        x-show="selectedValues.length > 0 && ! disabled"
                        x-cloak
                        x-on:click.stop="clear()"
                        @disabled($disabled)
                        aria-label="{{ __('ui.select.clear') }}"
                        class="shrink-0 rounded-sm p-1 text-zinc-400 hover:text-zinc-800 disabled:pointer-events-none disabled:opacity-50 dark:hover:text-zinc-200 [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                    >
                        <flux:icon name="x-mark" variant="micro" class="size-4" />
                    </button>
                @endif

                <flux:icon name="chevron-down" variant="micro" class="shrink-0 text-zinc-400" />
            </div>
        @else
            <div class="relative">
                <flux:input
                    id="{{ $triggerId }}"
                    readonly
                    :placeholder="$placeholder"
                    :size="$size"
                    :icon="$icon"
                    :invalid="$invalid"
                    :disabled="$disabled"
                    class:input="{{ $clearable ? 'pe-16' : 'pe-10' }}"
                    x-bind:value="display"
                    x-on:click="onTriggerClick()"
                    x-on:focus="onTriggerFocus()"
                    x-on:keydown="onListboxKeydown($event)"
                    x-bind:aria-expanded="open"
                    x-bind:aria-activedescendant="activeId"
                    aria-controls="{{ $popoverId }}"
                    aria-haspopup="listbox"
                    aria-autocomplete="none"
                    :aria-label="$accessibleLabel"
                    autocomplete="off"
                    role="combobox"
                />

                <div class="pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1 text-zinc-400">
                    @if ($clearable)
                        <button
                            type="button"
                            x-show="selectedValues.length > 0 && ! disabled"
                            x-cloak
                            x-on:click.stop="clear()"
                            @disabled($disabled)
                            aria-label="{{ __('ui.select.clear') }}"
                            class="pointer-events-auto rounded-sm p-1 hover:text-zinc-800 disabled:pointer-events-none disabled:opacity-50 dark:hover:text-zinc-200 [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                        >
                            <flux:icon name="x-mark" variant="micro" class="size-4" />
                        </button>
                    @endif

                    <flux:icon name="chevron-down" variant="micro" class="size-4" />
                </div>
            </div>
        @endif

        <div
            id="{{ $popoverId }}"
            popover="manual"
            data-ui-popover
            class="z-50 min-w-48 overflow-hidden rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-600 dark:bg-zinc-700 {{ $optionsClass }}"
        >
            <div
                id="{{ $listboxId }}"
                role="listbox"
                aria-multiselectable="{{ $multiple || $variant === 'pillbox' ? 'true' : 'false' }}"
                x-show="! emptyVisible"
                x-on:click="selectFromEvent($event)"
                class="max-h-60 overflow-y-auto"
            >
                {{ $slot }}
            </div>

            <x-ui.select.option.empty />
        </div>
    </div>
</flux:with-field>
