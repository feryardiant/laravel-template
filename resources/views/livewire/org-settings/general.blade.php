<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Manage your organization settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading level="2" class="sr-only">{{ __('General Settings') }}</flux:heading>

    <x-org-settings.layout :heading="__('General')" :subheading="__('Update your organization profile')">
        <form wire:submit="update" class="my-6 w-full space-y-6">
            <x-ui.file-upload wire:model="logo" accept="image/*" :label="__('Organization Logo')">
                <x-ui.file-upload.avatar :url="$logo ?? $this->logoPreviewUrl" shape="rounded" remove="clearLogo" />
            </x-ui.file-upload>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </x-org-settings.layout>
</section>
