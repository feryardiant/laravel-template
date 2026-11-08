@props([
    'multiple' => false,
    'accept' => null,
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'badge' => null,
    'error' => null,
    'disabled' => false,
])

@php
    $wireModel = $attributes->wire('model');
    $model = $wireModel->value();
    $model = is_string($model) && $model !== '' ? $model : null;
    $name = $attributes->get('name');
    $isMultiple = (bool) $multiple;
    $inputName = $name === null ? null : ($isMultiple ? $name.'[]' : $name);
    $inputId = 'ui-file-upload-'.\Illuminate\Support\Str::random(12).'-input';
    $invalid = $error !== null || ($model !== null && $errors->has($model));
    $accessibleLabel = $label ?? $attributes->get('aria-label') ?? __('ui.file-upload.drop');

    $config = [
        'disabled' => (bool) $disabled,
        'property' => $model,
        'labels' => [
            'uploading' => __('ui.file-upload.uploading'),
        ],
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
    :error:message="$error"
>
    <div
        x-data="uiFileUpload(@js($config), typeof $wire === 'undefined' ? null : $wire)"
        x-init="init($el)"
        data-ui-file-upload
        @if ($disabled) data-disabled @endif
        @if ($invalid) data-invalid @endif
        x-bind:data-loading="loading || null"
        x-bind:style="progressStyle()"
        x-on:livewire-upload-start="onUploadStart()"
        x-on:livewire-upload-progress="onUploadProgress($event)"
        x-on:livewire-upload-finish="onUploadFinish()"
        x-on:livewire-upload-error="onUploadFinish()"
        class="relative"
    >
        <input
            id="{{ $inputId }}"
            type="file"
            @if ($model !== null) wire:model="{{ $model }}" @elseif ($inputName !== null) name="{{ $inputName }}" @endif
            @if ($isMultiple) multiple @endif
            @if ($accept !== null) accept="{{ $accept }}" @endif
            @disabled($disabled)
            tabindex="-1"
            aria-label="{{ $accessibleLabel }}"
            class="sr-only"
        />

        {{ $slot }}
    </div>
</flux:with-field>
