@props([
    'value' => '',
    'type' => 'text',
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'badge' => null,
    'placeholder' => null,
    'size' => null,
    'variant' => 'outline',
    'icon' => null,
    'iconTrailing' => null,
    'clearable' => false,
    'disabled' => false,
    'readonly' => false,
    'invalid' => null,
    'containerClass' => null,
    'inputClass' => null,
    'searchUrl' => null,
    'minLength' => 1,
    'debounce' => 300,
    'empty' => null,
    'loading' => null,
])

@php
    $wireModel = $attributes->wire('model');
    $model = $wireModel->value();
    $model = is_string($model) && $model !== '' ? $model : null;
    $live = $wireModel->hasModifier('live');
    $name = $attributes->get('name');
    $invalid ??= $model !== null && $errors->has($model);

    $key = \Illuminate\Support\Str::random(12);
    $triggerId = 'ui-autocomplete-'.$key.'-trigger';
    $popoverId = 'ui-autocomplete-'.$key.'-popover';
    $listboxId = 'ui-autocomplete-'.$key.'-listbox';
    $isPlain = $model === null && $name !== null;
    $formInputId = $isPlain ? 'ui-autocomplete-'.$key.'-input' : null;

    $empty ??= __('ui.autocomplete.no-results');
    $loading ??= __('ui.autocomplete.loading');
    $accessibleLabel = $label ?? $attributes->get('aria-label') ?? $placeholder ?? __('ui.common.select');

    $config = [
        'value' => $value,
        'model' => $model,
        'live' => $live,
        'disabled' => (bool) $disabled,
        'readonly' => (bool) $readonly,
        'searchUrl' => $searchUrl,
        'minLength' => (int) $minLength,
        'debounce' => (int) $debounce,
        'empty' => $empty,
        'loading' => $loading,
        'triggerId' => $triggerId,
        'popoverId' => $popoverId,
        'listboxId' => $listboxId,
        'formInputId' => $formInputId,
    ];

    $rootAttributes = $attributes->filter(fn ($value, $key) => ! str_starts_with($key, 'wire:model'));
    $inputClasses = trim(($inputClass ?? '').' '.($clearable ? 'pe-10' : ''));
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
        x-data="uiAutocomplete(@js($config), typeof $wire === 'undefined' ? null : $wire)"
        x-init="init()"
        wire:ignore
        data-ui-autocomplete
        @if ($disabled) data-disabled @endif
        @if ($invalid) data-invalid @endif
        class="relative"
    >
        @if ($formInputId !== null)
            <input type="hidden" id="{{ $formInputId }}" name="{{ $name }}" value="{{ $value }}" />
        @endif

        <div class="relative">
            <flux:input
                id="{{ $triggerId }}"
                :type="$type"
                :placeholder="$placeholder"
                :size="$size"
                :variant="$variant"
                :icon="$icon"
                :icon-trailing="$iconTrailing"
                :invalid="$invalid"
                :disabled="$disabled"
                :readonly="$readonly"
                class:input="{{ $inputClasses }}"
                x-bind:value="query"
                x-on:input="onInput($event)"
                x-on:focus="onFocus()"
                x-on:keydown="onKeydown($event)"
                x-bind:aria-expanded="open"
                x-bind:aria-activedescendant="activeId"
                aria-controls="{{ $popoverId }}"
                aria-autocomplete="list"
                aria-haspopup="listbox"
                :aria-label="$accessibleLabel"
                autocomplete="off"
                role="combobox"
            />

            @if ($clearable)
                <button
                    type="button"
                    x-show="query.length > 0 && ! disabled"
                    x-cloak
                    x-on:click.stop="clear()"
                    @disabled($disabled)
                    aria-label="{{ __('ui.autocomplete.clear') }}"
                    class="absolute end-2 top-1/2 -translate-y-1/2 rounded-sm p-1 text-zinc-400 hover:text-zinc-800 disabled:pointer-events-none disabled:opacity-50 dark:hover:text-zinc-200 [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                >
                    <flux:icon name="x-mark" variant="micro" class="size-4" />
                </button>
            @endif
        </div>

        <div
            id="{{ $popoverId }}"
            popover="manual"
            data-ui-popover
            class="z-50 min-w-48 overflow-hidden rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-600 dark:bg-zinc-700 {{ $containerClass }}"
        >
            <div
                id="{{ $listboxId }}"
                role="listbox"
                x-show="! emptyVisible && ! loading"
                x-on:click="selectFromEvent($event)"
                class="max-h-60 overflow-y-auto"
            >
                @if ($searchUrl)
                    <template x-for="(item, index) in results" :key="item.label + index">
                        <div
                            role="option"
                            tabindex="-1"
                            data-ui-option
                            x-bind:data-value="item.label"
                            x-bind:data-label="item.label"
                            x-bind:data-selected-label="item.label"
                            x-text="item.label"
                            aria-selected="false"
                            class="flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm text-zinc-800 data-active:bg-zinc-100 dark:text-white dark:data-active:bg-zinc-600 [@media(hover:none)]:min-h-11"
                        ></div>
                    </template>
                @else
                    {{ $slot }}
                @endif
            </div>

            <div
                x-show="loading"
                x-cloak
                role="status"
                class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400"
                x-text="loadingText"
            ></div>

            <div
                x-show="emptyVisible && ! loading"
                x-cloak
                role="status"
                class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400"
                x-text="emptyText"
            ></div>
        </div>
    </div>
</flux:with-field>
