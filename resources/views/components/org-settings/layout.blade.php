<div class="flex items-start max-md:flex-col">
    @php($organization = app(\App\Support\CurrentOrganization::class)->get(auth()->user()))

    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Organization settings') }}">
            <flux:navlist.item :href="route('org-settings.general')" wire:navigate>{{ __('General') }}</flux:navlist.item>
            @if ($organization !== null)
                <flux:navlist.item :href="route('org-settings.features')" wire:navigate>{{ __('Features') }}</flux:navlist.item>
            @endif
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
