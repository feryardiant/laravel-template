<x-modal
    mode="confirm"
    wire:model.self="showModal"
    :title="__('Remove members')"
    :subtitle="__('Remove :count selected members from this organization?', ['count' => count($ids)])"
>
    <x-slot:actions>
        <flux:button variant="danger" wire:click="delete" data-test="confirm-remove-members">{{ __('Remove members') }}</flux:button>
    </x-slot:actions>
</x-modal>
