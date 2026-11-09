@props([
    'status' => null,
])

@aware([
    'mode' => 'vertical',
    'align' => 'center',
])

@php
    $mode = in_array($mode, ['vertical', 'horizontal'], true) ? $mode : 'vertical';
    $isHorizontal = $mode === 'horizontal';

    $align = $attributes->has('align') ? $attributes->get('align') : $align;
    $align = in_array($align, ['start', 'baseline', 'center', 'end'], true) ? $align : 'center';

    $status = in_array($status, ['complete', 'current', 'incomplete'], true) ? $status : null;

    $icon = $attributes->pluck('indicator:icon');
    $variant = $attributes->pluck('indicator:variant');
    $indicatorClass = $attributes->pluck('class:indicator');

    $isBare = $variant === 'bare' && $icon !== null;
    $hasCustomIndicator = $indicatorClass !== null;

    $indicatorColor = match ($status) {
        'complete' => 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900',
        'current' => 'bg-accent text-accent-foreground',
        default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300',
    };

    $dotColor = match ($status) {
        'complete' => 'bg-zinc-800 dark:bg-zinc-200',
        'current' => 'bg-accent',
        default => 'bg-zinc-300 dark:bg-zinc-600',
    };

    // Bare icons fill their wrapper so `class:indicator="size-8"` scales the glyph
    // too; filled indicators keep the padded 1rem icon unless the wrapper is sized.
    $iconClasses = match (true) {
        $isBare => 'size-full',
        $hasCustomIndicator => 'size-1/2',
        default => 'size-4',
    };

    $statusLabel = match ($status) {
        'complete' => __('ui.timeline.complete'),
        'current' => __('ui.timeline.current'),
        'incomplete' => __('ui.timeline.incomplete'),
        default => null,
    };

    $lineColor = $status === 'complete' ? 'bg-accent' : 'bg-zinc-200 dark:bg-zinc-700';
    $lineThickness = $isHorizontal ? 'h-px' : 'w-px';

    $topLine = match ($align) {
        'start' => 'hidden',
        'baseline' => ($isHorizontal ? 'w-3' : 'h-3').' shrink-0 '.$lineThickness.' '.$lineColor,
        default => 'flex-1 '.$lineThickness.' '.$lineColor,
    };

    $bottomLine = match ($align) {
        'end' => 'hidden',
        default => 'flex-1 '.$lineThickness.' '.$lineColor,
    };
@endphp

<li
    data-ui-timeline-item
    @if ($status !== null) data-status="{{ $status }}" @endif
    @if ($status === 'current') aria-current="step" @endif
    data-align="{{ $align }}"
    {{ $attributes->except(['align'])->class([
        'flex',
        'flex-col flex-1' => $isHorizontal,
        'shrink-0' => ! $isHorizontal,
    ]) }}
>
    @if ($statusLabel !== null)
        <span class="sr-only">{{ $statusLabel }}</span>
    @endif
    <div
        data-ui-timeline-rail
        @class([
            'flex items-center justify-center',
            ($isHorizontal ? 'flex-row pb-2' : 'flex-col pr-2'),
        ])
    >
        <span data-ui-timeline-line="top" class="{{ $topLine }}"></span>

        <span
            data-ui-timeline-indicator
            data-variant="{{ $isBare ? 'bare' : 'default' }}"
            aria-hidden="true"
            @class([
                'relative z-10 flex shrink-0 items-center',
                match (true) {
                    $isBare => 'items-center justify-center'.($hasCustomIndicator ? '' : ' size-6 text-zinc-400 dark:text-zinc-500'),
                    $icon !== null => 'rounded-full items-center justify-center'.($hasCustomIndicator ? '' : ' size-6 '.$indicatorColor),
                    default => 'rounded-full'.($hasCustomIndicator ? '' : ' size-2.5 '.$dotColor),
                },
                $indicatorClass,
            ])
        >
            @if ($icon !== null)
                <flux:icon :name="$icon" variant="micro" class="{{ $iconClasses }}" />
            @endif
        </span>

        <span data-ui-timeline-line="bottom" class="{{ $bottomLine }}"></span>
    </div>

    <div
        @class([
            'min-w-0 flex-1',
            match ($align) {
                'start' => $isHorizontal ? 'pb-2 text-left' : 'pb-2',
                'center' => $isHorizontal ? 'px-2 text-center' : 'py-2',
                'end' => $isHorizontal ? 'pt-2 text-right' : 'pt-2',
            },
        ])
    >
        {{ $slot }}
    </div>
</li>
