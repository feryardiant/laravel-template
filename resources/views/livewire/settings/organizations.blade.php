<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Organizations') }}</flux:heading>

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
                                            wire:click="openApproval({{ $organization->id }})"
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
                                            wire:click="cancelLeaveRequest({{ $pendingRequest->id }})"
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
                                            wire:click="confirmRemove({{ $organization->id }})"
                                        >
                                            {{ __('Remove organization') }}
                                        </flux:button>
                                    @endif
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="confirmLeave({{ $organization->id }}, @js($organization->name))"
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

    <flux:modal
        wire:model.self="showLeaveModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'leave-organization-heading')"
    >
        <form wire:submit="leave" class="space-y-6">
            <div>
                <flux:heading id="leave-organization-heading" size="lg">
                    @if ($leaveNeedsApproval)
                        {{ __('Request to leave :organization?', ['organization' => $leavingOrganizationName ?? __('this organization')]) }}
                    @else
                        {{ __('Leave :organization?', ['organization' => $leavingOrganizationName ?? __('this organization')]) }}
                    @endif
                </flux:heading>

                <flux:subheading>
                    @if ($leaveNeedsApproval)
                        {{ __('An owner must approve this request. You keep access until they respond.') }}
                    @else
                        {{ __('You will lose access to this organization. An owner or admin can add you back later.') }}
                    @endif
                </flux:subheading>
            </div>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">
                    {{ $leaveNeedsApproval ? __('Request to leave') : __('Leave organization') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        wire:model.self="showRemoveModal"
        class="md:w-lg"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'remove-organization-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="remove-organization-heading" size="lg">{{ __('Remove organization') }}</flux:heading>
                <flux:subheading>{{ $removingOrganizationName }}</flux:subheading>
            </div>

            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('This action cannot be undone.')">
                <flux:callout.text>
                    {{ __('All data owned by :name will be permanently deleted, including its members, contacts, addresses, and logo. All members will lose access.', ['name' => $removingOrganizationName]) }}
                </flux:callout.text>
            </flux:callout>

            <flux:input
                wire:model.live.debounce.300ms="confirmation"
                :label="__('Type :name to confirm', ['name' => $removingOrganizationName])"
                autocomplete="off"
            />

            <flux:error name="confirmation" />

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="remove"
                    :disabled="$confirmation !== $removingOrganizationName"
                >
                    {{ __('Remove organization') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        wire:model.self="showApprovalModal"
        class="md:w-lg"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'review-approval-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="review-approval-heading" size="lg">
                    {{ $approvalType === \App\Enums\ApprovalRequestType::OwnershipTransfer->value
                        ? __('Ownership transfer request')
                        : __('Leave request') }}
                </flux:heading>
                <flux:subheading>{{ $approvalOrganizationName }}</flux:subheading>
            </div>

            <flux:text>
                @if ($approvalType === \App\Enums\ApprovalRequestType::OwnershipTransfer->value)
                    {{ __(':owner wants to transfer ownership of :organization to you. Accepting makes you the owner and the current owner an Admin.', ['owner' => $approvalRequesterName, 'organization' => $approvalOrganizationName]) }}
                @else
                    {{ __(':name requested to leave :organization. Approving removes their access immediately.', ['name' => $approvalRequesterName, 'organization' => $approvalOrganizationName]) }}
                @endif
            </flux:text>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Dismiss') }}</flux:button>
                </flux:modal.close>

                <div class="flex items-center gap-2">
                    <flux:button variant="danger" wire:click="declineApproval">{{ __('Decline') }}</flux:button>
                    <flux:button variant="primary" wire:click="acceptApproval">
                        {{ $approvalType === \App\Enums\ApprovalRequestType::OwnershipTransfer->value ? __('Accept') : __('Approve') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <livewire:settings.organizations.create />
</section>
