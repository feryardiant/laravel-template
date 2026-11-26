<x-modal
    wire:model.self="showModal"
    :title="$this->isPromotion ? __('Promote member') : __('Demote member')"
    :subtitle="$this->isPromotion ? __('Promote :name to Admin?', ['name' => $personName ?? __('this member')]) : __('Demote :name to Member?', ['name' => $personName ?? __('this member')])"
>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button
                :variant="$this->isPromotion ? 'primary' : 'danger'"
                wire:click="change"
            >
                {{ $this->isPromotion ? __('Promote admin') : __('Demote admin') }}
            </flux:button>
        </div>
    </div>
</x-modal>
