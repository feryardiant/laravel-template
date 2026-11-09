@props([
    'mode' => 'vertical',
    'align' => 'center',
])

@php
    $mode = in_array($mode, ['vertical', 'horizontal'], true) ? $mode : 'vertical';
    $align = in_array($align, ['start', 'baseline', 'center', 'end'], true) ? $align : 'center';
    $isHorizontal = $mode === 'horizontal';
@endphp

{{-- A timeline is an ordered list; the connector lines and indicator marks are
     decorative and hidden from assistive technology. --}}
<ol
    role="list"
    data-ui-timeline
    data-mode="{{ $mode }}"
    data-align="{{ $align }}"
    @if($isHorizontal) tabindex="0" @endif
    {{ $attributes->class([
        'flex',
        'flex-col' => ! $isHorizontal,
        'items-start overflow-x-auto pb-1 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-hidden' => $isHorizontal,
        '[&>li:first-child>[data-ui-timeline-rail]>[data-ui-timeline-line=top]]:bg-transparent',
        '[&>li:last-child>[data-ui-timeline-rail]>[data-ui-timeline-line=bottom]]:bg-transparent',
    ]) }}
>
    {{ $slot }}
</ol>
