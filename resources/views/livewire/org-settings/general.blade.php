<x-page :heading="__('Organization settings')" :subheading="__('Manage your organization settings')">
    <x-org-settings.layout :heading="__('General')" :subheading="__('Update your organization profile')">
        <form wire:submit="update" class="my-6 w-full space-y-6">
            <x-ui.file-upload wire:model="logo" accept="image/*" :label="__('Organization Logo')">
                <x-ui.file-upload.avatar :url="$logo ?? $this->logoPreviewUrl" shape="rounded" remove="clearLogo" />
            </x-ui.file-upload>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        @if ($this->isOwner)
            <x-ui.section variant="danger" :title="__('Danger Zone')">
                <livewire:org-settings.general.transfer-ownership />
                <livewire:org-settings.general.remove-organization />
            </x-ui.section>
        @endif
    </x-org-settings.layout>
</x-page>
