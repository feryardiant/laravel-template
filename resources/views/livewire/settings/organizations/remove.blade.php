<x-modal
    mode="confirm"
    wire:model.self="showModal"
    width="md:w-lg"
    :title="__('Remove organization')"
    :subtitle="$organizationName"
>
    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('This action cannot be undone.')">
        <flux:callout.text>
            {{ __('All data owned by :name will be permanently deleted, including its members, contacts, addresses, and logo. All members will lose access.', ['name' => $organizationName]) }}
        </flux:callout.text>
    </flux:callout>

    <flux:input
        wire:model.live.debounce.300ms="confirmation"
        :label="__('Type :name to confirm', ['name' => $organizationName])"
        autocomplete="off"
    />

    <flux:error name="confirmation" />

    <x-slot:actions>
        <flux:button
            variant="danger"
            wire:click="remove"
            :disabled="$confirmation !== $organizationName"
        >
            {{ __('Remove organization') }}
        </flux:button>
    </x-slot:actions>
</x-modal>
