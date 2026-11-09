@props([
    'label',
])

<div role="group" aria-label="{{ $label }}" class="py-1">
    <div class="px-2 py-1 text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ $label }}</div>

    {{ $slot }}
</div>
