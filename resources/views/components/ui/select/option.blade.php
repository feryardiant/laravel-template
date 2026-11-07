@props([
    'value' => null,
    'label' => null,
    'selectedLabel' => null,
    'keywords' => null,
    'icon' => null,
    'description' => null,
    'avatar' => null,
    'disabled' => false,
])

@php
    $text = trim(strip_tags((string) $slot));
    $optionValue = $value ?? $text;
    $optionLabel = $label ?? $text;
    $selectedLabel ??= $optionLabel;
@endphp

<div
    role="option"
    tabindex="-1"
    data-ui-option
    data-value="{{ $optionValue }}"
    data-label="{{ $optionLabel }}"
    data-selected-label="{{ $selectedLabel }}"
    data-keywords="{{ $keywords }}"
    aria-selected="false"
    @if ($disabled) aria-disabled="true" data-disabled @endif
    @class([
        'flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-start text-sm text-zinc-800 dark:text-white',
        'data-active:bg-zinc-100 dark:data-active:bg-zinc-600',
        'data-selected:font-medium',
        '[@media(hover:none)]:min-h-11',
        'opacity-50' => $disabled,
    ])
>
    @if ($avatar)
        <flux:avatar :src="$avatar" size="sm" class="mt-0.5 shrink-0" />
    @elseif ($icon)
        <flux:icon :name="$icon" variant="mini" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
    @endif

    <div class="min-w-0 flex-1">
        <div class="truncate">{{ $slot }}</div>

        @if ($description)
            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</div>
        @endif
    </div>

    <flux:icon
        name="check"
        variant="micro"
        data-selected-indicator
        hidden
        class="mt-0.5 size-4 shrink-0 text-accent-content"
    />
</div>
