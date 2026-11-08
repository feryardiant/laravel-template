@props([
    'heading' => null,
    'text' => null,
    'icon' => 'cloud-arrow-up',
    'inline' => false,
    'withProgress' => false,
])

@php
    $heading ??= $inline ? __('ui.file-upload.drop-inline') : __('ui.file-upload.drop');
    $textId = 'ui-file-upload-hint-'.Str::random(10);
@endphp

<div
    data-ui-dropzone
    role="button"
    x-bind:tabindex="disabled ? '-1' : '0'"
    x-bind:aria-disabled="disabled ? 'true' : null"
    aria-label="{{ $heading }}"
    @if ($text !== null) aria-describedby="{{ $textId }}" @endif
    x-on:click="openPicker()"
    x-on:keydown.enter.prevent="openPicker()"
    x-on:keydown.space.prevent="openPicker()"
    x-on:dragenter.prevent="onDragEnter($event)"
    x-on:dragover.prevent="onDragOver($event)"
    x-on:dragleave.prevent="onDragLeave($event)"
    x-on:drop.prevent="onDrop($event)"
    x-bind:data-dragging="dragging || null"
    x-bind:data-loading="loading || null"
    x-bind:class="disabled ? 'cursor-default opacity-50' : 'cursor-pointer'"
    class="rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50/50 transition-colors data-dragging:border-accent data-dragging:bg-accent/5 dark:border-white/15 dark:bg-white/5 dark:data-dragging:border-accent {{ $inline ? 'px-3 py-2' : 'px-6 py-8' }}"
>
    <div class="{{ $inline ? 'flex items-center gap-2' : 'flex flex-col items-center gap-2 text-center' }}">
        <flux:icon :name="$icon" variant="outline" class="{{ $inline ? 'size-5' : 'size-8' }} shrink-0 text-zinc-400" />

        <div class="min-w-0">
            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $heading }}</div>

            @if ($text !== null)
                <div id="{{ $textId }}" class="text-xs text-zinc-500 dark:text-zinc-400">{{ $text }}</div>
            @endif
        </div>
    </div>

    @if ($withProgress)
        <div
            x-show="loading"
            x-cloak
            class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10"
        >
            <div
                class="h-full rounded-full bg-accent transition-[width] duration-150"
                :style="`width: ${progress}%`"
            ></div>
        </div>
    @endif

    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="announcement"></div>
</div>
