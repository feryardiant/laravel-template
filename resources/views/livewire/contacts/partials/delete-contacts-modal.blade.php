<x-modal
    mode="confirm"
    wire:model.self="showModal"
    :title="__('Delete contacts')"
    :subtitle="__('Delete :count selected contacts?', ['count' => count($ids)])"
>
    <x-slot:actions>
        <flux:button variant="danger" wire:click="delete">{{ __('Delete contacts') }}</flux:button>
    </x-slot:actions>
</x-modal>
