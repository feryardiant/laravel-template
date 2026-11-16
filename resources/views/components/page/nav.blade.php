@props([
    'heading' => '',
    'subheading' => '',
    'width' => 'max-w-lg',
])

<div class="flex items-start max-md:flex-col gap-4">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        {{ $nav ?? '' }}
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <flux:heading level="2">{{ $heading }}</flux:heading>
                <flux:subheading>{{ $subheading }}</flux:subheading>
            </div>

            <div class="flex flex-row">{{ $actions ?? '' }}</div>
        </div>

        <div class="mt-5 w-full {{ $width }}">
            {{ $slot }}
        </div>
    </div>
</div>
