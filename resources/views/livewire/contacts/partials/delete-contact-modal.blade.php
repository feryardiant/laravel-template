<x-modal
    wire:model.self="showModal"
    :title="__('Delete contact')"
    :subtitle="__('Delete :name from your contacts?', ['name' => $contactName ?? __('this contact')])"
>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="delete">
                {{ __('Delete contact') }}
            </flux:button>
        </div>
    </div>
</x-modal>
