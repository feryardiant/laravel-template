@props([
    'label' => null,
    'circle' => false,
])

@php
    $shape = $circle ? 'rounded-full' : 'rounded-md';
    $baseClass = 'inline-flex size-8 cursor-pointer items-center justify-center '.$shape.' text-zinc-400 transition-colors hover:bg-zinc-800/5 hover:text-zinc-800 disabled:pointer-events-none disabled:opacity-50 dark:hover:bg-white/10 dark:hover:text-white [@media(hover:none)]:min-h-11 [@media(hover:none)]:min-w-11';
@endphp

<button
    data-ui-remove
    {{ $attributes->merge([
        'type' => 'button',
        'aria-label' => $label ?? __('ui.file-upload.remove'),
        'class' => $baseClass,
    ]) }}
>
    <flux:icon name="x-mark" variant="micro" class="size-4" />
</button>
