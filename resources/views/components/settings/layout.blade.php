@props(['heading' => '', 'subheading' => '', 'width' => 'max-w-lg'])

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('preferences.edit')" wire:navigate>{{ __('Preferences') }}</flux:navlist.item>
            <flux:navlist.item :href="route('organizations.index')" wire:navigate>{{ __('Organizations') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 flex-col self-stretch max-md:pt-6">
        <div class="flex flex-row">
            <div class="flex-1">
                <flux:heading>{{ $heading }}</flux:heading>
                <flux:subheading>{{ $subheading }}</flux:subheading>
            </div>

            <div class="flex flex-row">{{ $actions ?? '' }}</div>
        </div>

        <div class="flex flex-col gap 2 mt-5 w-full {{ $width }}">
            {{ $slot }}
        </div>
    </div>
</div>
