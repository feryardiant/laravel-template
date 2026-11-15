@props([
    'heading' => null,
    'subheading' => null,
    'separator' => true,
])

<div {{ $attributes->class('flex w-full flex-1 flex-col gap-6') }}>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex w-full items-center gap-3">
            {{ $leading ?? '' }}

            <div class="w-full">
                <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>

                @if ($subheading instanceof \Illuminate\View\ComponentSlot)
                    {{ $subheading }}
                @elseif (filled($subheading))
                    <flux:subheading>{{ $subheading }}</flux:subheading>
                @endif
            </div>
        </div>

        {{ $actions ?? '' }}
    </div>

    @if ($separator)
        <flux:separator variant="subtle" />
    @endif

    {{ $slot }}
</div>
