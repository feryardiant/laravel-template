<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Manage your organization settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading level="2" class="sr-only">{{ __('Features') }}</flux:heading>

    <x-org-settings.layout :heading="__('Features')" :subheading="__('Control platform-wide features')">
        <flux:switch
            wire:model.live="registration"
            :label="__('Public registration')"
            :description="__('Allow visitors to create an account and a tenant organization.')"
        />
    </x-org-settings.layout>
</section>
