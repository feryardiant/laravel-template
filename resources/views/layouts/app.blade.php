@php($organization = app(\App\Support\CurrentOrganization::class)->get(auth()->user()))

@if ($organization !== null)
    <x-layouts::app.sidebar container:class="p-4" :title="$title ?? null">
        <flux:main role="main">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@else
    <x-layouts::app.header container:class="p-4" :title="$title ?? null">
        <flux:main role="main">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.header>
@endif
