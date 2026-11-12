@php($organization = app(\App\Support\CurrentOrganization::class)->get(auth()->user()))

<x-layouts::app :title="__('Home')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        @if ($organization === null)
            <div class="flex flex-1 items-center justify-center">
                <h1 class="mx-auto max-w-xl text-balance text-center text-4xl font-extralight text-zinc-400 dark:text-zinc-500">
                    {{ __("You're not part of an organization yet.") }}
                </h1>
            </div>
        @else
            <x-auth-session-status class="text-center" :status="session('status')" />

            <div class="relative w-full">
                <flux:heading size="xl" level="1">{{ __('Home') }}</flux:heading>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
            <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        @endif
    </div>
</x-layouts::app>
