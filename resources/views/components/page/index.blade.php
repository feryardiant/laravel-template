@props([
    'heading' => null,
    'subheading' => null,
    'separator' => true,
])

@php
    $headerClass = Flux::classes('flex flex-col gap-4 @4xl/page:flex-row @4xl/page:items-start @4xl/page:justify-between')
        ->add($attributes->get('header:class'));

    $attributes = $attributes->except('header:class');
@endphp

<div {{ $attributes->class('@container/page flex w-full flex-1 flex-col gap-6') }}>
    <header class="{{ $headerClass }}">
        <div class="flex w-full min-w-0 items-center gap-3">
            {{ $leading ?? '' }}

            <div class="w-full min-w-0">
                <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>

                @if ($subheading instanceof \Illuminate\View\ComponentSlot)
                    {{ $subheading }}
                @elseif (filled($subheading))
                    <flux:subheading>{{ $subheading }}</flux:subheading>
                @endif
            </div>
        </div>

        @if (isset($actions))
            @if ($actions instanceof \Illuminate\View\ComponentSlot && $actions->attributes->isNotEmpty())
                <div {{ $actions->attributes->class('min-w-0 @4xl/page:shrink-0') }}>{{ $actions }}</div>
            @else
                {{ $actions }}
            @endif
        @endif
    </header>

    @if ($separator)
        <flux:separator variant="subtle" />
    @endif

    {{ $slot }}
</div>
