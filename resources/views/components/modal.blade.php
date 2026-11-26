@props([
    'id' => uniqid(),
    'flyout' => false,
    'position' => 'left',
    'variant' => 'default',
    'mode' => 'default',
    'width' => 'md:w-96',
    'title' => null,
    'subtitle' => null,
    'cancel' => null,
])

@php
    $width = $flyout ? 'md:w-lg' : $width;
    $mode = in_array($mode, ['form', 'confirm', 'default'], true) ? $mode : 'default';
    $wireSubmit = $attributes->get('wire:submit');
    $actions ??= '';
    $formFooter = $flyout
        ? '-mx-6 px-6 before:absolute before:inset-x-0 before:top-full before:h-6 before:bg-white before:content-[\'\'] dark:before:bg-zinc-800'
        : '-mx-6 px-6';
    $hasHeading = filled($title);
    $headingId = $hasHeading ? $id.'-heading' : null;
    $labelledBy = $hasHeading
        ? '$el.querySelector(\'dialog\')?.setAttribute(\'aria-labelledby\', \''.$headingId.'\')'
        : null;
@endphp

<flux:modal
    :$flyout
    :$position
    :$variant
    {{ $attributes->except('wire:submit')->class(['flex flex-col gap-6 w-full p-6!', $width]) }}
    x-init="{{ $labelledBy }}"
>
    @if ($hasHeading)
        <header class="pb-6 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading :id="$headingId" size="lg" :class="$flyout ? 'pe-8' : null">
                {{ $title }}
            </flux:heading>

            @if (filled($subtitle))
                <flux:subheading>{{ $subtitle }}</flux:subheading>
            @endif
        </header>
    @endif

    @if ($mode === 'confirm')
        @if (filled($slot))
            <div class="flex flex-col gap-6">
                {{ $slot }}
            </div>
        @endif

        <footer class="flex flex-row items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ $cancel ?? __('Cancel') }}</flux:button>
            </flux:modal.close>

            <div class="flex flex-row gap-6">
                {{ $actions }}
            </div>
        </footer>
    @elseif ($mode === 'form')
        <form wire:submit="{{ $wireSubmit }}" class="-mb-6 flex flex-1 flex-col">
            <div class="flex flex-col gap-6">
                {{ $slot }}
            </div>

            <footer class="sticky bottom-0 {{ $formFooter }} mt-auto flex items-center justify-between bg-white pt-6 dark:bg-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ $cancel ?? __('Cancel') }}</flux:button>
                </flux:modal.close>

                <div class="flex flex-row gap-6">
                    {{ $actions }}
                </div>
            </footer>
        </form>
    @else
        <div class="flex flex-col gap-6">
            {{ $slot }}
        </div>
    @endif
</flux:modal>
