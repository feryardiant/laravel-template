<x-modal
    mode="confirm"
    wire:model.self="showModal"
    :title="$needsApproval ? __('Request to leave :organization?', ['organization' => $organizationName ?? __('this organization')]) : __('Leave :organization?', ['organization' => $organizationName ?? __('this organization')])"
    :subtitle="$needsApproval ? __('An owner must approve this request. You keep access until they respond.') : __('You will lose access to this organization. An owner or admin can add you back later.')"
>
    <x-slot:actions>
        <flux:button variant="danger" wire:click="leave">
            {{ $needsApproval ? __('Request to leave') : __('Leave organization') }}
        </flux:button>
    </x-slot:actions>
</x-modal>
