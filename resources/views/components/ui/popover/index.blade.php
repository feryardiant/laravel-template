@props([
    'id',
    'role' => null,
    'label' => null,
])

{{-- Thin shell: callers own the trigger, content and value; createPopover owns
     the open state, positioning and ARIA. --}}
<div
    id="{{ $id }}"
    popover="manual"
    data-ui-popover
    @if ($role) role="{{ $role }}" @endif
    @if ($role === 'dialog') aria-modal="false" @endif
    @if ($label) aria-label="{{ $label }}" @endif
    {{ $attributes->class('z-50 select-none') }}
>
    {{ $slot }}
</div>
