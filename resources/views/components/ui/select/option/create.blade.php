@props([
    'minLength' => 1,
])

@php
    $id = 'ui-select-create-'.\Illuminate\Support\Str::random(10);
    $resolvedMinLength = max(1, (int) $minLength);
@endphp

<div
    {{ $attributes->merge([
        'id' => $id,
        'role' => 'option',
        'tabindex' => '-1',
        'aria-selected' => 'false',
        'data-min-length' => $resolvedMinLength,
        'x-bind:data-active' => 'createActive || null',
        'x-on:click.stop' => 'create()',
        'class' => 'flex w-full cursor-pointer items-center rounded-md px-2 py-1.5 text-start text-sm text-zinc-800 data-active:bg-zinc-100 dark:text-white dark:data-active:bg-zinc-600 [@media(hover:none)]:min-h-11',
    ]) }}
    data-ui-create
    x-show="createVisible"
    x-cloak
>
    {{ $slot }}
</div>
