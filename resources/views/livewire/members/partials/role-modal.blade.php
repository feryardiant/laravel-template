<x-modal
    mode="confirm"
    wire:model.self="showModal"
    :title="$this->isPromotion ? __('Promote member') : __('Demote member')"
    :subtitle="$this->isPromotion ? __('Promote :name to Admin?', ['name' => $personName ?? __('this member')]) : __('Demote :name to Member?', ['name' => $personName ?? __('this member')])"
>
    <x-slot:actions>
        <flux:button
            :variant="$this->isPromotion ? 'primary' : 'danger'"
            wire:click="change"
        >
            {{ $this->isPromotion ? __('Promote admin') : __('Demote admin') }}
        </flux:button>
    </x-slot:actions>
</x-modal>
