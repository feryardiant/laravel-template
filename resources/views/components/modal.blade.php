@props([
    'id' => uniqid(),
    'flyout' => false,
    'position' => 'left',
    'variant' => 'default',
    'width' => 'md:w-96',
    'title' => null,
    'subtitle' => null,
])

@php
    $width = $flyout ? 'md:w-lg' : $width;
    $hasHeading = filled($title);
    $headingId = $hasHeading ? $id.'-heading' : null;
    $labelledBy = $hasHeading
        ? '$el.querySelector(\'dialog\')?.setAttribute(\'aria-labelledby\', \''.$headingId.'\')'
        : null;
@endphp

<div>
    <flux:modal
        :$flyout
        :$position
        :$variant
        {{ $attributes->class(['space-y-6 w-full', $width]) }}
        x-init="{{ $labelledBy }}"
    >
        @if ($hasHeading)
            <div>
                <flux:heading :id="$headingId" size="lg" :class="$flyout ? 'pe-8' : null">
                    {{ $title }}
                </flux:heading>

                @if (filled($subtitle))
                    <flux:subheading>{{ $subtitle }}</flux:subheading>
                @endif
            </div>
        @endif

        {{ $slot }}
    </flux:modal>
</div>
