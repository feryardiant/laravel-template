@props([
    'title' => null,
    'variant' => 'default',
])

@php
    $variant = in_array($variant, ['default', 'danger'], true) ? $variant : 'default';
@endphp

<section {{ $attributes->class('space-y-4') }}>
    @if ($title !== null)
        <flux:heading size="lg">{{ $title }}</flux:heading>
    @endif

    <div
        @class([
            'divide-y overflow-hidden rounded-xl border',
            match ($variant) {
                'danger' => 'divide-red-200/70 border-red-200 dark:divide-red-500/20 dark:border-red-500/30',
                default => 'divide-zinc-200 border-zinc-200 dark:divide-white/10 dark:border-white/10',
            },
        ])
    >
        {{ $slot }}
    </div>
</section>
