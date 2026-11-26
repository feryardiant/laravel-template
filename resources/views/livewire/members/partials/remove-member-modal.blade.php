<x-modal
    mode="confirm"
    wire:model.self="showModal"
    :title="__('Remove member')"
    :subtitle="__('Remove :name from this organization? The person and any login credential are preserved.', ['name' => $personName ?? __('this member')])"
>
    <x-slot:actions>
        <flux:button variant="danger" wire:click="delete">{{ __('Remove member') }}</flux:button>
    </x-slot:actions>
</x-modal>
