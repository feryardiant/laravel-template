<div class="flex w-full flex-1 flex-col gap-6">
    @php($roleColor = $role?->isOwner() ? 'amber' : ($role?->isAdmin() ? 'blue' : 'zinc'))

    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:avatar :initials="$person->initials()" :src="$person->photo_url" size="lg" />

            <div class="relative w-full">
                <flux:heading size="xl" level="1">
                    {{ $person->name }}
                    @if ($role !== null)
                        <flux:badge :color="$roleColor">{{ $role->label() }}</flux:badge>
                    @endif
                </flux:heading>

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
            </div>
        </div>

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
    </div>

    <flux:separator variant="subtle" />

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

        <div>
            <flux:text>{{ __('Joined at') }}</flux:text>
            <flux:heading><x-local-time :date="$joinedAt" /></flux:heading>
        </div>
    </flux:card>

    @if ($canManage)
        <livewire:members.edit :person="$person" />
    @endif

    <flux:modal
        wire:model.self="showRoleModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'role-change-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="role-change-heading" size="lg">
                    {{ $this->roleChangeIsPromotion ? __('Promote member') : __('Demote member') }}
                </flux:heading>

                <flux:subheading>
                    {{ $this->roleChangeIsPromotion
                        ? __('Promote :name to Admin?', ['name' => $person->name])
                        : __('Demote :name to Member?', ['name' => $person->name]) }}
                </flux:subheading>
            </div>

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
    </flux:modal>
</div>
