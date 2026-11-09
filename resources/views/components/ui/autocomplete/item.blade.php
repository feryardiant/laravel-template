@props([
    'disabled' => false,
])

@php
    $text = trim(strip_tags((string) $slot));
@endphp

<div
    role="option"
    tabindex="-1"
    data-ui-option
    data-value="{{ $text }}"
    data-label="{{ $text }}"
    data-selected-label="{{ $text }}"
    aria-selected="false"
    @if ($disabled) aria-disabled="true" data-disabled @endif
    @class([
        'flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm text-zinc-800 dark:text-white',
        'data-active:bg-zinc-100 dark:data-active:bg-zinc-600',
        '[@media(hover:none)]:min-h-11',
        'opacity-50' => $disabled,
    ])
>
    {{ $slot }}
</div>
