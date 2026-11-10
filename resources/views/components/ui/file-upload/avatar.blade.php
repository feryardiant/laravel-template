@props([
    'shape' => 'circle',
    'url' => null,
    'alt' => null,
    'on-click' => null,
    'remove' => null,
])

@php
    $shape = in_array($shape, ['circle', 'rounded'], true) ? $shape : 'circle';

    if ($url instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
        $previewUrl = $url->isPreviewable() ? $url->temporaryUrl() : null;
    } else {
        $previewUrl = $url;
    }
@endphp

<div class="relative w-fit">
    <x-ui.file-upload.dropzone bare {{ $attributes->except(['bare']) }}>
        <div
            @class([
                'relative flex size-24 items-center justify-center overflow-hidden border border-zinc-200 bg-zinc-100 transition-colors group-data-dragging:border-accent group-data-dragging:bg-accent/5 hover:bg-zinc-200 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15',
                match ($shape) {
                    'rounded' => 'rounded-xl',
                    'circle' => 'rounded-full'
                }
            ])
        >
            @if ($previewUrl)
                <img src="{{ $previewUrl }}" alt="{{ $alt }}" class="size-full object-cover" />
            @else
                <flux:icon name="user" variant="solid" class="size-10 text-zinc-400" />
            @endif
        </div>
    </x-ui.file-upload.dropzone>

    @if ($previewUrl && $remove)
        <x-ui.file-upload.item.remove
            circle
            wire:click="{{ $remove }}"
            :aria-label="__('ui.file-upload.remove')"
            class="absolute end-0 bottom-0 z-10 border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-800"
        />
    @elseif (! $previewUrl)
        <span class="pointer-events-none absolute end-0 bottom-0 rounded-full bg-white p-0.5 dark:bg-zinc-800">
            <flux:icon name="arrow-up-circle" variant="solid" class="size-6 text-zinc-500" />
        </span>
    @endif
</div>
