<flux:dropdown position="bottom" align="start" class="w-full">
    <flux:sidebar.profile
        :name="$current?->name ?? config('app.name')"
        icon:trailing="chevrons-up-down"
        data-test="organization-menu-button"
    >
        <x-slot:avatar>
            <flux:avatar
                :initials="$current?->initials() ?? ''"
                size="sm"
            />
        </x-slot:avatar>
    </flux:sidebar.profile>

    <flux:menu class="w-56">
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar :initials="$current?->initials() ?? ''" size="sm" />

            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $current?->name ?? __('No organization') }}</flux:heading>
            </div>
        </div>

        <flux:menu.group :heading="__('Your organizations')">
            @forelse ($organizations as $organization)
                <flux:menu.radio
                    wire:key="organization-switch-{{ $organization->id }}"
                    wire:click="switchOrganization({{ $organization->id }})"
                    data-test="organization-switch-{{ $organization->id }}"
                    :checked="$current?->is($organization)"
                >
                    <span class="truncate">{{ $organization->name }}</span>
                </flux:menu.radio>
            @empty
                <flux:menu.item disabled>{{ __('No organizations') }}</flux:menu.item>
            @endforelse
        </flux:menu.group>

        @if ($role?->isOwner() || $role?->isAdmin())
            <flux:menu.item :href="route('org-settings.general')" icon="cog" wire:navigate>
                {{ __('Organization settings') }}
            </flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
