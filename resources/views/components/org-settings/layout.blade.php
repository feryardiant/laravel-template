@props(['heading' => '', 'subheading' => '', 'width' => 'max-w-lg'])

@php($organization = app(\App\Support\CurrentOrganization::class)->get(auth()->user()))
@php($user = auth()->user())
@php($role = $organization !== null && $user instanceof \App\Models\User ? $user->roleIn($organization) : null)

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Organization settings') }}">
            <flux:navlist.item :href="route('org-settings.general')" wire:navigate>{{ __('General') }}</flux:navlist.item>
            @if ($organization !== null)
                <flux:navlist.item :href="route('org-settings.features')" wire:navigate>{{ __('Features') }}</flux:navlist.item>
            @endif
            @if ($organization?->isRootTenant() === true && ($role?->isOwner() || $role?->isAdmin()))
                <flux:navlist.item :href="route('org-settings.subsidiaries')" wire:navigate>{{ __('Subsidiaries') }}</flux:navlist.item>
            @endif
            @if ($role?->isOwner())
                <flux:navlist.item :href="route('org-settings.ownership-history')" wire:navigate>{{ __('Ownership history') }}</flux:navlist.item>
            @endif
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <div class="flex flex-row">
            <div class="flex-1">
                <flux:heading>{{ $heading }}</flux:heading>
                <flux:subheading>{{ $subheading }}</flux:subheading>
            </div>

            <div class="flex flex-row">{{ $actions ?? '' }}</div>
        </div>

        <div class="mt-5 w-full {{ $width }}">
            {{ $slot }}
        </div>
    </div>
</div>
