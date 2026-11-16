<x-page>
    @php($roleColor = $role?->isOwner() ? 'amber' : ($role?->isAdmin() ? 'blue' : 'zinc'))

    <x-slot:leading>
        <flux:avatar :initials="$person->initials()" :src="$person->photo_url" size="lg" />
    </x-slot:leading>

    <x-slot:heading>
        {{ $person->name }}
        @if ($role !== null)
            <flux:badge :color="$roleColor">{{ $role->label() }}</flux:badge>
        @endif
    </x-slot:heading>

    <x-slot:subheading>
        <flux:subheading class="flex flex-wrap items-center gap-2">
            <span><x-local-time :date="$joinedAt" /></span>

            @if ($person->user !== null)
                <span>|</span>
                <span>{{ $person->user->email }}</span>
                <x-verification-badge :user="$person->user" />
            @else
                <span>|</span>
                <span>{{ __('No login credential') }}</span>
            @endif
        </flux:subheading>
    </x-slot:subheading>

    <x-slot:actions>
        @if ($canManage)
            <div class="hidden items-center gap-2 sm:flex">
                @if ($role?->isMember())
                    <flux:button icon="arrow-up-circle" wire:click="confirmPromote">
                        {{ __('Promote admin') }}
                    </flux:button>
                @elseif ($role?->isAdmin())
                    <flux:button icon="arrow-down-circle" wire:click="confirmDemote">
                        {{ __('Demote admin') }}
                    </flux:button>
                @endif

                <flux:button variant="primary" icon="pencil-square" wire:click="openEdit">
                    {{ __('Edit member') }}
                </flux:button>
            </div>

            <flux:dropdown align="end" class="sm:hidden">
                <flux:button
                    icon="ellipsis-horizontal"
                    square
                    :aria-label="__('Actions for :name', ['name' => $person->name])"
                />

                <flux:menu>
                    @if ($role?->isMember())
                        <flux:menu.item icon="arrow-up-circle" wire:click="confirmPromote">
                            {{ __('Promote admin') }}
                        </flux:menu.item>
                    @elseif ($role?->isAdmin())
                        <flux:menu.item icon="arrow-down-circle" wire:click="confirmDemote">
                            {{ __('Demote admin') }}
                        </flux:menu.item>
                    @endif

                    <flux:menu.item icon="pencil-square" wire:click="openEdit">
                        {{ __('Edit member') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </x-slot:actions>

    <flux:card class="max-w-lg space-y-4">
        <div>
            <flux:text>{{ __('Name') }}</flux:text>
            <flux:heading>{{ $person->name }}</flux:heading>
        </div>

        <div>
            <flux:text>{{ __('Email') }}</flux:text>
            <flux:heading>{{ $person->user?->email ?? __('No login credential') }}</flux:heading>
        </div>

        <div>
            <flux:text>{{ __('Role') }}</flux:text>
            <flux:heading>{{ $role?->label() ?? '—' }}</flux:heading>
        </div>

        @if (\App\Support\Feature\ContactGender::enabled($organization))
            <div>
                <flux:text>{{ __('Gender') }}</flux:text>
                <flux:heading>{{ $person->gender?->label() ?? '—' }}</flux:heading>
            </div>
        @endif

        <div>
            <flux:text>{{ __('Joined at') }}</flux:text>
            <flux:heading><x-local-time :date="$joinedAt" /></flux:heading>
        </div>
    </flux:card>

    @php($address = $person->addresses()->first())

    @if ($address !== null)
        <x-address-summary :address="$address" />
    @endif

    <x-relationship-history :periods="$this->history" />

    @if ($canManage)
        <livewire:members.edit :person="$person" />
    @endif

    <x-modal
        wire:model.self="showRoleModal"
        :title="$this->roleChangeIsPromotion ? __('Promote member') : __('Demote member')"
        :subtitle="$this->roleChangeIsPromotion ? __('Promote :name to Admin?', ['name' => $person->name]) : __('Demote :name to Member?', ['name' => $person->name])"
    >
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    :variant="$this->roleChangeIsPromotion ? 'primary' : 'danger'"
                    wire:click="changeRoleConfirmed"
                >
                    {{ $this->roleChangeIsPromotion ? __('Promote admin') : __('Demote admin') }}
                </flux:button>
            </div>
        </div>
    </x-modal>
</x-page>
