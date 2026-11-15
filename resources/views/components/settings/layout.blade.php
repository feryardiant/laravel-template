@props(['heading' => '', 'subheading' => '', 'width' => 'max-w-lg'])

<x-page.nav :$heading :$subheading :$width>
    <x-slot:nav>
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('preferences.edit')" wire:navigate>{{ __('Preferences') }}</flux:navlist.item>
            <flux:navlist.item :href="route('organizations.index')" wire:navigate>{{ __('Organizations') }}</flux:navlist.item>
        </flux:navlist>
    </x-slot:nav>

    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    {{ $slot }}
</x-page.nav>
