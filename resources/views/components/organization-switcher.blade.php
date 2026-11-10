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

        <flux:menu.separator />

        @forelse ($organizations as $organization)
            <form method="POST" action="{{ route('organizations.switch', $organization) }}" class="w-full">
                @csrf

                <flux:menu.item
                    as="button"
                    type="submit"
                    class="w-full cursor-pointer"
                    data-test="organization-switch-{{ $organization->id }}"
                >
                    <span class="flex items-center gap-2">
                        <flux:avatar size="sm" :initials="$organization->initials()" />
                        <span class="truncate">{{ $organization->name }}</span>

                        @if ($current?->is($organization))
                            <flux:icon.check class="ms-auto size-4" />
                        @endif
                    </span>
                </flux:menu.item>
            </form>
        @empty
            <flux:menu.item disabled>{{ __('No organizations') }}</flux:menu.item>
        @endforelse

        @if ($role?->isOwner() || $role?->isAdmin())
            <flux:menu.separator />

            <flux:menu.item :href="route('org-settings')" icon="cog" wire:navigate>
                {{ __('Organization settings') }}
            </flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
