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
    'required' => false,
    'autofocus' => false,
    'invalid' => null,
    'containerClass' => null,
    'inputClass' => null,
    'searchUrl' => null,
    'valueModel' => null,
    'minLength' => 1,
    'debounce' => 300,
    'empty' => null,
    'loading' => null,
    'kbd' => null,
    'copyable' => false,
    'viewable' => false,
    'mask' => null,
    'maskDynamic' => null,
])

@php
    $maskDynamic ??= $attributes->pluck('mask:dynamic');
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
        'valueModel' => $valueModel,
        'minLength' => (int) $minLength,
        'debounce' => (int) $debounce,
        'empty' => $empty,
        'loading' => $loading,
        'triggerId' => $triggerId,
        'popoverId' => $popoverId,
        'listboxId' => $listboxId,
        'formInputId' => $formInputId,
    ];

    $class = $attributes->get('class');
    $rootAttributes = $attributes->filter(
        fn ($value, $key) => ! str_starts_with($key, 'wire:model') && $key !== 'class' && $key !== 'mask:dynamic',
    );
    $trailingCount = count(array_filter([
        (bool) $iconTrailing,
        (bool) $kbd,
        (bool) $clearable,
        (bool) $copyable,
        (bool) $viewable,
    ]));
    $trailingPadding = match ($trailingCount) {
        0 => 'pe-3',
        1 => 'pe-10',
        2 => 'pe-16',
        3 => 'pe-23',
        4 => 'pe-30',
        5 => 'pe-37',
        default => 'pe-44',
    };
    $touchPadding = match ($trailingCount) {
        0 => '',
        1 => '[@media(hover:none)]:pe-14',
        2 => '[@media(hover:none)]:pe-26',
        3 => '[@media(hover:none)]:pe-38',
        4 => '[@media(hover:none)]:pe-50',
        default => '[@media(hover:none)]:pe-62',
    };
    $inputClasses = trim(($inputClass ?? '').' '.$trailingPadding.' '.$touchPadding);
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
        @class(['relative', $class])
    >
        @if ($formInputId !== null)
            <input type="hidden" id="{{ $formInputId }}" name="{{ $name }}" value="{{ $value }}" @disabled($disabled) />
        @endif

        <div class="relative">
            <flux:input
                id="{{ $triggerId }}"
                :type="$type"
                :placeholder="$placeholder"
                :size="$size"
                :variant="$variant"
                :icon="$icon"
                :mask="$mask"
                :mask-dynamic="$maskDynamic"
                :invalid="$invalid"
                :disabled="$disabled"
                :readonly="$readonly"
                :required="$required ?: null"
                :autofocus="$autofocus ?: null"
                class:input="{{ $inputClasses }}"
                x-model="query"
                x-on:input="onInput($event)"
                x-on:focus="onFocus()"
                x-on:keydown="onKeydown($event)"
                x-bind:aria-activedescendant="activeId || null"
                x-bind:type="revealed ? 'text' : '{{ $type }}'"
                aria-controls="{{ $listboxId }}"
                aria-autocomplete="list"
                aria-haspopup="listbox"
                :aria-label="$accessibleLabel"
                autocomplete="off"
                role="combobox"
            />

            @if ($trailingCount > 0)
                <div class="pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1">
                    @if ($iconTrailing)
                        @if (is_string($iconTrailing))
                            <flux:icon :name="$iconTrailing" variant="mini" class="size-5 text-zinc-400/75 dark:text-white/60" />
                        @else
                            {{ $iconTrailing }}
                        @endif
                    @endif

                    @if ($kbd)
                        <span data-ui-kbd class="text-xs text-zinc-500 dark:text-zinc-400">{{ $kbd }}</span>
                    @endif

                    @if ($copyable)
                        <flux:button
                            type="button"
                            variant="ghost"
                            size="xs"
                            data-ui-copy
                            x-on:click.stop="copy()"
                            x-bind:aria-label="copied ? '{{ __('ui.autocomplete.copied') }}' : '{{ __('ui.autocomplete.copy') }}'"
                            x-bind:data-copied="copied ? 'true' : null"
                            aria-label="{{ __('ui.autocomplete.copy') }}"
                            class="pointer-events-auto [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                        >
                            <flux:icon name="clipboard-document" variant="micro" class="size-4" x-show="! copied" />
                            <flux:icon name="clipboard-document-check" variant="micro" class="size-4" x-show="copied" x-cloak />
                        </flux:button>
                    @endif

                    @if ($viewable)
                        <flux:button
                            type="button"
                            variant="ghost"
                            size="xs"
                            data-ui-viewable
                            x-on:click.stop="toggleReveal()"
                            x-bind:aria-label="revealed ? '{{ __('ui.autocomplete.hide-password') }}' : '{{ __('ui.autocomplete.show-password') }}'"
                            x-bind:data-revealed="revealed ? 'true' : null"
                            aria-label="{{ __('ui.autocomplete.show-password') }}"
                            class="pointer-events-auto [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11"
                        >
                            <flux:icon name="eye" variant="micro" class="size-4" x-show="! revealed" />
                            <flux:icon name="eye-slash" variant="micro" class="size-4" x-show="revealed" x-cloak />
                        </flux:button>
                    @endif

                    @if ($clearable)
                        <flux:button
                            type="button"
                            variant="ghost"
                            icon="x-mark"
                            size="xs"
                            x-cloak
                            x-show="query.length > 0 && ! disabled && ! readonly"
                            x-on:click.stop="clear()"
                            :disabled="$disabled"
                            aria-label="{{ __('ui.autocomplete.clear') }}"
                            class="pointer-events-auto"
                        />
                    @endif
                </div>
            @endif
        </div>

        <x-ui.popover
            :id="$popoverId"
            class="min-w-48 overflow-hidden p-1 {{ $containerClass }}"
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
                            x-bind:data-value="item.value ?? item.label"
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
                class="px-3 py-2 text-sm text-zinc-500 select-text dark:text-zinc-400"
                x-text="loadingText"
            ></div>

            <div
                x-show="emptyVisible && ! loading"
                x-cloak
                role="status"
                class="px-3 py-2 text-sm text-zinc-500 select-text dark:text-zinc-400"
                x-text="emptyText"
            ></div>
        </x-ui.popover>
    </div>
</flux:with-field>
