<x-ui.section.item
    :title="__('Transfer Ownership')"
    :description="__('Transfer this organization to another member')"
>
    <x-slot:details>
        @if ($this->pendingApproval !== null)
            <flux:callout variant="warning" icon="clock" :heading="__('Approval request pending')">
                <flux:callout.text>
                    <flux:badge size="sm" color="amber" class="mb-2">
                        {{ $this->pendingApproval->status->label() }}
                    </flux:badge>

                    @if ($this->pendingApproval->type === \App\Enums\ApprovalRequestType::OwnershipTransfer)
                        {{ __('Pending transfer to :name', ['name' => $this->pendingApproval->approver->name]) }}.
                    @else
                        {{ __('Leave request from :name', ['name' => $this->pendingApproval->requester->name]) }}.
                    @endif
                    {{ __('Expires on') }}
                    <x-local-time :date="$this->pendingApproval->expires_at" format="d M Y H:i" />.
                </flux:callout.text>
            </flux:callout>
        @endif
    </x-slot:details>

    @if ($this->pendingApproval?->type === \App\Enums\ApprovalRequestType::OwnershipTransfer)
        <flux:button variant="danger" wire:click="cancelTransfer">
            {{ __('Cancel transfer') }}
        </flux:button>
    @elseif ($this->pendingApproval !== null)
        <flux:button
            variant="danger"
            disabled
            :tooltip="__('Resolve the pending approval request first.')"
        >
            {{ __('Transfer') }}
        </flux:button>
    @elseif ($this->members->isEmpty())
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

    <x-modal
        wire:model.self="showTransferModal"
        width="md:w-lg"
        :title="__('Transfer ownership')"
        :subtitle="$organization?->name"
    >
        <div class="space-y-6">
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('You will become an Admin.')">
                <flux:callout.text>
                    {{ __('The selected member must accept the transfer. Until then, you remain the owner.') }}
                </flux:callout.text>
            </flux:callout>

            <x-ui.select
                wire:model="recipientId"
                :label="__('New owner')"
                :placeholder="__('Select a member')"
            >
                @foreach ($this->members as $member)
                    <x-ui.select.option :value="$member->getKey()">
                        {{ $member->name }} ({{ $member->user?->email }})
                    </x-ui.select.option>
                @endforeach
            </x-ui.select>

            <flux:error name="recipientId" />

            <flux:input
                wire:model.live.debounce.300ms="confirmation"
                :label="__('Type :name to confirm', ['name' => $organization?->name])"
                autocomplete="off"
            />

            <flux:error name="confirmation" />

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="transfer"
                    :disabled="$recipientId === null || $confirmation !== $organization?->name"
                >
                    {{ __('Transfer ownership') }}
                </flux:button>
            </div>
        </div>
    </x-modal>
</x-ui.section.item>
