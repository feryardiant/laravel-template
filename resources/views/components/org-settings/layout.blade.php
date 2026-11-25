@props(['heading' => '', 'subheading' => '', 'width' => 'max-w-lg'])

@php($organization = app(\App\Support\CurrentOrganization::class)->get())

<x-page.nav :$heading :$subheading :$width>
    <x-slot:nav>
        <flux:navlist aria-label="{{ __('Organization settings') }}">
            @if ($organization !== null)
                @can('update', $organization)
                    <flux:navlist.item :href="route('org-settings.general')" wire:navigate>{{ __('General') }}</flux:navlist.item>
                @endcan
                @can('manageFeatures', $organization)
                    <flux:navlist.item :href="route('org-settings.features')" wire:navigate>{{ __('Features') }}</flux:navlist.item>
                @endcan
                @can('viewSubsidiaries', $organization)
                    <flux:navlist.item :href="route('org-settings.subsidiaries')" wire:navigate>{{ __('Subsidiaries') }}</flux:navlist.item>
                @endcan
                @can('viewApprovals', $organization)
                    <flux:navlist.item :href="route('org-settings.approval-history')" wire:navigate>{{ __('Approvals history') }}</flux:navlist.item>
                @endcan
                @can('viewAudit', $organization)
                    <flux:navlist.item :href="route('org-settings.audit')" wire:navigate>{{ __('Audit log') }}</flux:navlist.item>
                @endcan
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
