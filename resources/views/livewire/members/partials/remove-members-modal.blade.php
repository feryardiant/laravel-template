<x-modal
    wire:model.self="showModal"
    :title="__('Remove members')"
    :subtitle="__('Remove :count selected members from this organization?', ['count' => count($ids)])"
>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="delete" data-test="confirm-remove-members">
                {{ __('Remove members') }}
            </flux:button>
        </div>
    </div>
</x-modal>
