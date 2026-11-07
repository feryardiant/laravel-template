@props([
    'whenLoading' => null,
])

<div
    x-show="emptyVisible"
    x-cloak
    role="status"
    class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400"
>
    {{ $slot->isEmpty() ? __('ui.select.no-results') : $slot }}
</div>
