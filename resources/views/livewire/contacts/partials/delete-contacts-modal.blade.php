<x-modal
    wire:model.self="showModal"
    :title="__('Delete contacts')"
    :subtitle="__('Delete :count selected contacts?', ['count' => count($ids)])"
>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="delete">
                {{ __('Delete contacts') }}
            </flux:button>
        </div>
    </div>
</x-modal>
