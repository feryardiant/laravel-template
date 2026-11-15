<x-modal
    wire:model.self="showModal"
    :title="$leaveNeedsApproval ? __('Request to leave :organization?', ['organization' => $leavingOrganizationName ?? __('this organization')]) : __('Leave :organization?', ['organization' => $leavingOrganizationName ?? __('this organization')])"
    :subtitle="$leaveNeedsApproval ? __('An owner must approve this request. You keep access until they respond.') : __('You will lose access to this organization. An owner or admin can add you back later.')"
>
    <form wire:submit="leave" class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit">
                {{ $leaveNeedsApproval ? __('Request to leave') : __('Leave organization') }}
            </flux:button>
        </div>
    </form>
</x-modal>
