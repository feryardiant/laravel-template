@props([
    'heading' => '',
    'subheading' => '',
    'width' => 'max-w-lg',
])

@php
    $headerClass = Flux::classes('flex flex-col gap-4 @5xl/main:flex-row @5xl/main:items-start @5xl/main:justify-between')
        ->add($attributes->get('header:class'));

    $actionsAttributes = ($actions ?? null) instanceof \Illuminate\View\ComponentSlot
        ? $actions->attributes
        : new \Illuminate\View\ComponentAttributeBag;
@endphp

<div class="flex items-start max-md:flex-col gap-4">
    <aside class="me-10 w-full pb-4 sm:w-1/5 md:w-3xs">
        {{ $nav ?? '' }}
    </aside>

    <flux:separator class="md:hidden" />

    <div class="@container/main flex-1 self-stretch">
        <header class="{{ $headerClass }}">
            <div class="min-w-0 flex-1">
                <flux:heading level="2">{{ $heading }}</flux:heading>
                <flux:subheading>{{ $subheading }}</flux:subheading>
            </div>

            <div {{ $actionsAttributes->class('flex min-w-0 flex-wrap @5xl/main:shrink-0 gap-3') }}>{{ $actions ?? '' }}</div>
        </header>

        <div class="mt-5 w-full {{ $width }}">
            {{ $slot }}
        </div>
    </div>
</div>
