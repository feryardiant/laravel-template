@props([
    'file' => null,
    'heading' => null,
    'text' => null,
    'image' => null,
    'size' => null,
    'icon' => 'document',
    'invalid' => false,
])

@php
    if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
        $heading ??= $file->getClientOriginalName();
        $size ??= $file->getSize();
        $image ??= $file->isPreviewable() && str_starts_with((string) $file->getMimeType(), 'image/')
            ? $file->temporaryUrl()
            : null;
    }

    $formattedSize = $size === null ? null : \Illuminate\Support\Number::fileSize((int) $size, precision: 1);
    $interactive = $attributes->whereStartsWith('wire:click')->isNotEmpty();
    $class = $attributes->get('class');
@endphp

<div
    {{ $attributes->except('class') }}
    data-ui-file-upload-item
    @if ($invalid) data-invalid @endif
    @class([
        'flex items-center gap-3 rounded-lg border bg-white p-2.5 dark:bg-white/5',
        'border-zinc-200 dark:border-white/10' => ! $invalid,
        'border-red-500 dark:border-red-500' => $invalid,
        'cursor-pointer' => $interactive,
        $class,
    ])
>
    @if ($image !== null)
        <img src="{{ $image }}" alt="" class="size-10 shrink-0 rounded-md object-cover" />
    @else
        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
            <flux:icon :name="$icon" variant="outline" class="size-5" />
        </div>
    @endif

    <div class="min-w-0 flex-1">
        @if ($heading !== null)
            <div class="truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $heading }}</div>
        @endif

        @if ($text !== null)
            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $text }}</div>
        @endif

        @if ($formattedSize !== null)
            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $formattedSize }}</div>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 items-center gap-1">{{ $slot }}</div>
    @endif
</div>
