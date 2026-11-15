<x-ui.section.item
    :title="__('Delete account')"
    :description="__('Delete your account and all of its resources')"
>
    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <x-modal
        name="confirm-user-deletion"
        :show="$errors->isNotEmpty()"
        focusable
        width="max-w-lg"
        :title="__('Are you sure you want to delete your account?')"
        :subtitle="__('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.')"
    >
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Delete account') }}</flux:button>
            </div>
        </form>
    </x-modal>
</x-ui.section.item>
