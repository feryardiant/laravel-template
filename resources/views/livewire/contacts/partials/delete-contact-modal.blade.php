<x-modal
    mode="confirm"
    wire:model.self="showModal"
    :title="__('Delete contact')"
    :subtitle="__('Delete :name from your contacts?', ['name' => $contactName ?? __('this contact')])"
>
    <x-slot:actions>
        <flux:button variant="danger" wire:click="delete">{{ __('Delete contact') }}</flux:button>
    </x-slot:actions>
</x-modal>
