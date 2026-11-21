@props(['heading' => '', 'subheading' => '', 'width' => 'max-w-lg'])

@php($organization = app(\App\Support\CurrentOrganization::class)->get(auth()->user()))
@php($user = auth()->user())
@php($role = $organization !== null && $user instanceof \App\Models\User ? $user->roleIn($organization) : null)

<x-page.nav :$heading :$subheading :$width>
    <x-slot:nav>
        <flux:navlist aria-label="{{ __('Organization settings') }}">
            <flux:navlist.item :href="route('org-settings.general')" wire:navigate>{{ __('General') }}</flux:navlist.item>
            @if ($organization !== null)
                <flux:navlist.item :href="route('org-settings.features')" wire:navigate>{{ __('Features') }}</flux:navlist.item>
            @endif
            @if ($organization?->isRootTenant() === true && ($role?->isOwner() || $role?->isAdmin()))
                <flux:navlist.item :href="route('org-settings.subsidiaries')" wire:navigate>{{ __('Subsidiaries') }}</flux:navlist.item>
            @endif
            @if ($role?->isOwner())
                <flux:navlist.item :href="route('org-settings.approval-history')" wire:navigate>{{ __('Approvals history') }}</flux:navlist.item>
            @endif
            @if ($role?->isOwner() || $role?->isAdmin())
                <flux:navlist.item :href="route('org-settings.audit')" wire:navigate>{{ __('Audit log') }}</flux:navlist.item>
            @endif
        </flux:navlist>
    </x-slot:nav>

    @isset($actions)
        @php($actionsAttributes = $actions instanceof \Illuminate\View\ComponentSlot ? $actions->attributes : new \Illuminate\View\ComponentAttributeBag)

        <x-slot:actions :attributes="$actionsAttributes">
            {{ $actions }}
        </x-slot:actions>
    @endisset

    {{ $slot }}
</x-page.nav>
