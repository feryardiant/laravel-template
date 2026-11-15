<x-modal
    wire:model.self="showModal"
    width="md:w-lg"
    :title="$approvalType === \App\Enums\ApprovalRequestType::OwnershipTransfer->value ? __('Ownership transfer request') : __('Leave request')"
    :subtitle="$organizationName"
>
    <div class="space-y-6">
        <flux:text>
            @if ($approvalType === \App\Enums\ApprovalRequestType::OwnershipTransfer->value)
                {{ __(':owner wants to transfer ownership of :organization to you. Accepting makes you the owner and the current owner an Admin.', ['owner' => $requesterName, 'organization' => $organizationName]) }}
            @else
                {{ __(':name requested to leave :organization. Approving removes their access immediately.', ['name' => $requesterName, 'organization' => $organizationName]) }}
            @endif
        </flux:text>

        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Dismiss') }}</flux:button>
            </flux:modal.close>

            <div class="flex items-center gap-2">
                <flux:button variant="danger" wire:click="decline">{{ __('Decline') }}</flux:button>
                <flux:button variant="primary" wire:click="accept">
                    {{ $approvalType === \App\Enums\ApprovalRequestType::OwnershipTransfer->value ? __('Accept') : __('Approve') }}
                </flux:button>
            </div>
        </div>
    </div>
</x-modal>
