<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Manage your organization settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading level="2" class="sr-only">{{ __('General Settings') }}</flux:heading>

    <x-org-settings.layout :heading="__('General')" :subheading="__('Update your organization profile')">
        <form wire:submit="update" class="my-6 w-full space-y-6">
            <x-ui.file-upload wire:model="logo" accept="image/*" :label="__('Organization Logo')">
                <x-ui.file-upload.avatar :url="$logo ?? $this->logoPreviewUrl" shape="rounded" remove="clearLogo" />
            </x-ui.file-upload>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        @if ($this->isOwner)
            <x-ui.section variant="danger" :title="__('Danger Zone')">
                <x-ui.section.item
                    :title="__('Transfer Ownership')"
                    :description="__('Transfer this organization to another member')"
                >
                    <x-slot:details>
                        @if ($this->pendingTransfer !== null)
                            <flux:callout variant="warning" icon="clock" :heading="__('Ownership transfer pending')">
                                <flux:callout.text>
                                    <flux:badge size="sm" color="amber" class="mb-2">
                                        {{ $this->pendingTransfer->status->label() }}
                                    </flux:badge>

                                    {{ __('Pending transfer to :name', ['name' => $this->pendingTransfer->toPerson->name]) }}.
                                    {{ __('Expires on') }}
                                    <x-local-time :date="$this->pendingTransfer->expires_at" format="d M Y H:i" />.
                                </flux:callout.text>
                            </flux:callout>
                        @endif
                    </x-slot:details>

                    @if ($this->pendingTransfer !== null)
                        <flux:button variant="danger" wire:click="cancelTransfer">
                            {{ __('Cancel transfer') }}
                        </flux:button>
                    @elseif ($this->transferMembers->isEmpty())
                        <flux:button
                            variant="danger"
                            disabled
                            :tooltip="__('Add a member with a login credential first.')"
                        >
                            {{ __('Transfer') }}
                        </flux:button>
                    @else
                        <flux:button variant="danger" wire:click="confirmTransfer">
                            {{ __('Transfer') }}
                        </flux:button>
                    @endif
                </x-ui.section.item>

                <x-ui.section.item
                    :title="__('Delete Organization')"
                    :description="__('Once you delete an organization, there is no going back. Please be certain.')"
                >
                    @if ($organization?->isRootTenant() === true)
                        <flux:button
                            variant="danger"
                            disabled
                            :tooltip="__('The root organization cannot be deleted.')"
                        >
                            {{ __('Delete Organization') }}
                        </flux:button>
                    @else
                        <flux:button variant="danger" wire:click="confirmRemove">
                            {{ __('Delete Organization') }}
                        </flux:button>
                    @endif
                </x-ui.section.item>
            </x-ui.section>
        @endif
    </x-org-settings.layout>

    <flux:modal
        wire:model.self="showTransferModal"
        class="md:w-lg"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'transfer-ownership-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="transfer-ownership-heading" size="lg">{{ __('Transfer ownership') }}</flux:heading>
                <flux:subheading>{{ $organization?->name }}</flux:subheading>
            </div>

            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('You will become an Admin.')">
                <flux:callout.text>
                    {{ __('The selected member must accept the transfer. Until then, you remain the owner.') }}
                </flux:callout.text>
            </flux:callout>

            <x-ui.select
                wire:model="transferRecipientId"
                :label="__('New owner')"
                :placeholder="__('Select a member')"
            >
                @foreach ($this->transferMembers as $member)
                    <x-ui.select.option :value="$member->getKey()">
                        {{ $member->name }} ({{ $member->user?->email }})
                    </x-ui.select.option>
                @endforeach
            </x-ui.select>

            <flux:error name="transferRecipientId" />

            <flux:input
                wire:model.live.debounce.300ms="transferConfirmation"
                :label="__('Type :name to confirm', ['name' => $organization?->name])"
                autocomplete="off"
            />

            <flux:error name="transferConfirmation" />

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="transfer"
                    :disabled="$transferRecipientId === null || $transferConfirmation !== $organization?->name"
                >
                    {{ __('Transfer ownership') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        wire:model.self="showRemoveModal"
        class="md:w-lg"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'remove-organization-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="remove-organization-heading" size="lg">{{ __('Remove organization') }}</flux:heading>
                <flux:subheading>{{ $organization?->name }}</flux:subheading>
            </div>

            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('This action cannot be undone.')">
                <flux:callout.text>
                    {{ __('All data owned by :name will be permanently deleted, including its members, contacts, addresses, and logo. All members will lose access.', ['name' => $organization?->name]) }}
                </flux:callout.text>
            </flux:callout>

            <flux:input
                wire:model.live.debounce.300ms="removeConfirmation"
                :label="__('Type :name to confirm', ['name' => $organization?->name])"
                autocomplete="off"
            />

            <flux:error name="removeConfirmation" />

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="remove"
                    :disabled="$removeConfirmation !== $organization?->name"
                >
                    {{ __('Remove organization') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
