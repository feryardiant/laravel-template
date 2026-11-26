<x-modal
    mode="confirm"
    wire:model.self="showModal"
    :title="__('Remove subsidiary')"
    :subtitle="__('Remove :name?', ['name' => $removingName])"
>
    <flux:text>
        {{ __('This permanently deletes the organization, its members, contacts, addresses and logo. People and login credentials are kept, and the deletion runs in the background.') }}

        @if ($removingCount > 1)
            {{ __('This will also remove :count nested subsidiaries.', ['count' => $removingCount - 1]) }}
        @endif
    </flux:text>

    <x-slot:actions>
        <flux:button variant="danger" wire:click="remove">{{ __('Remove') }}</flux:button>
    </x-slot:actions>
</x-modal>
