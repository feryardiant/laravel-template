@props([
    'whenLoading' => null,
])

<div
    x-show="emptyVisible"
    x-cloak
    role="status"
    class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400"
>
    @if ($slot->isEmpty())
        <span x-text="emptyText"></span>
    @else
        {{ $slot }}
    @endif
</div>
