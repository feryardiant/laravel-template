@props(['title' => null, 'description' => null])

<div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
    @if ($title !== null)
        <div class="flex flex-1 flex-col gap-1">
            <flux:heading>{{ $title }}</flux:heading>

            @if ($description !== null)
                <flux:text>{{ $description }}</flux:text>
            @endif

            @if (isset($details))
                <div class="mt-3">{{ $details }}</div>
            @endif
        </div>
    @endif

    <div @class(['flex flex-wrap items-center gap-2', 'w-full flex-col' => $title === null])>
        {{ $slot }}
    </div>
</div>
