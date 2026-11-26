<x-modal
    wire:model.self="showModal"
    :title="__('Remove member')"
    :subtitle="__('Remove :name from this organization? The person and any login credential are preserved.', ['name' => $personName ?? __('this member')])"
>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="delete">
                {{ __('Remove member') }}
            </flux:button>
        </div>
    </div>
</x-modal>
