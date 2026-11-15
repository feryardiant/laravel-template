<x-page :heading="__('Settings')" :subheading="__('Manage your profile and account settings')">
    <x-settings.layout
        :heading="__('Organizations')"
        :subheading="__('Manage your memberships in the organizations you have joined')"
        width="max-w-none"
    >
        <x-slot:actions>
            <flux:button icon="plus" wire:click="$dispatch('create-organization')">
                {{ __('New organization') }}
            </flux:button>
        </x-slot>

        <div>
            @if ($this->memberships->isEmpty())
                <flux:text>{{ __('You are not a member of any organization.') }}</flux:text>
            @else
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($this->memberships as $membership)
                        @php($organization = $membership['organization'])
                        @php($role = $membership['role'])
                        @php($parent = $membership['parent'])
                        @php($kind = $membership['kind'])
                        @php($pendingApproval = $membership['pendingApproval'])
                        @php($pendingRequest = $membership['pendingRequest'])

                        <div
                            wire:key="organization-{{ $organization->id }}"
                            class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <flux:avatar
                                    :initials="$organization->initials()"
                                    :src="$organization->logo_url"
                                    :tooltip="$parent !== null ? __(':kind of :name', ['kind' => $kind?->label() ?? __('Subsidiary'), 'name' => $parent->name]) : null"
                                >
                                    @if ($parent !== null)
                                        <x-slot:badge>
                                            <flux:avatar
                                                :initials="$parent->initials()"
                                                :src="$parent->logo_url"
                                                class="size-3 text-[6px]"
                                            />
                                        </x-slot:badge>
                                    @endif
                                </flux:avatar>

                                <div class="min-w-0">
                                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                                        <flux:heading class="truncate">{{ $organization->name }}</flux:heading>

                                        @if ($role !== null)
                                            <flux:badge
                                                size="sm"
                                                :color="$role->isOwner() ? 'amber' : ($role->isAdmin() ? 'blue' : 'zinc')"
                                            >
                                                {{ $role->label() }}
                                            </flux:badge>
                                        @endif

                                        @if ($membership['isCurrent'])
                                            <flux:badge size="sm" color="zinc">{{ __('Current') }}</flux:badge>
                                        @endif
                                    </div>

                                    <flux:text class="text-xs" variant="subtle">
                                        <x-local-time :date="$organization->created_at" />
                                    </flux:text>

                                    @if ($parent !== null)
                                        <span class="sr-only">{{ __(':kind of :name', ['kind' => $kind?->label() ?? __('Subsidiary'), 'name' => $parent->name]) }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                @if ($membership['canManage'])
                                    <flux:button size="sm" wire:click="manage({{ $organization->id }})">
                                        {{ __('Settings') }}
                                    </flux:button>
                                @endif

                                @if ($membership['isDefault'])
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="star"
                                        disabled
                                        :tooltip="__('This is your default organization.')"
                                    >
                                        {{ __('Default') }}
                                    </flux:button>
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="star"
                                        wire:click="setDefault({{ $organization->id }})"
                                        :tooltip="__('Set this organization as your default.')"
                                    >
                                        {{ __('Set default') }}
                                    </flux:button>
                                @endif

                                @if ($pendingApproval !== null)
                                    <div class="flex items-center gap-2">
                                        <span class="relative flex size-2" aria-hidden="true">
                                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-sky-400 opacity-75"></span>
                                            <span class="relative inline-flex size-2 rounded-full bg-sky-500"></span>
                                        </span>

                                        <flux:text class="text-sm" variant="subtle">
                                            @if ($pendingApproval->type === \App\Enums\ApprovalRequestType::MembershipLeave)
                                                {{ __('Leave request from :name', ['name' => $pendingApproval->requester->name]) }}
                                            @else
                                                {{ __('Ownership transfer requested') }}
                                            @endif
                                        </flux:text>

                                        <flux:badge size="sm" color="sky">
                                            {{ $pendingApproval->status->label() }}
                                        </flux:badge>

                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            wire:click="$dispatch('respond', { organizationId: {{ $organization->id }} })"
                                        >
                                            {{ __('Respond') }}
                                        </flux:button>
                                    </div>
                                @elseif ($pendingRequest !== null && $pendingRequest->type === \App\Enums\ApprovalRequestType::MembershipLeave)
                                    <div class="flex items-center gap-2">
                                        <flux:text class="text-sm" variant="subtle">
                                            {{ __('Leave request pending') }}
                                        </flux:text>

                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            wire:click="$dispatch('cancel-leave-request', { approvalId: {{ $pendingRequest->id }} })"
                                        >
                                            {{ __('Cancel request') }}
                                        </flux:button>
                                    </div>
                                @elseif ($membership['isLastOwner'])
                                    @if ($organization->isRootTenant())
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            disabled
                                            :tooltip="__('You cannot leave while you are the last owner.')"
                                        >
                                            {{ __('Leave') }}
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            wire:click="$dispatch('remove-organization', { organizationId: {{ $organization->id }} })"
                                        >
                                            {{ __('Remove organization') }}
                                        </flux:button>
                                    @endif
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="$dispatch('leave-organization', { organizationId: {{ $organization->id }}, name: @js($organization->name) })"
                                    >
                                        {{ __('Leave') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-settings.layout>

    <livewire:settings.organizations.leave />

    <livewire:settings.organizations.remove />

    <livewire:settings.organizations.transfer-approval />

    <livewire:settings.organizations.create />
</x-page>
