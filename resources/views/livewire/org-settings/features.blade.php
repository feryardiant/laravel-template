<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Manage your organization settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading level="2" class="sr-only">{{ __('Features') }}</flux:heading>

    <x-org-settings.layout :heading="__('Features')" :subheading="__('Control platform and workspace features')">
        <x-ui.section>
            <x-ui.section.item>
                <flux:switch
                    wire:model.live="contactGender"
                    :label="__('Gender field in contact')"
                    :description="__('Show this field in contacts form.')"
                />
            </x-ui.section.item>

            <x-ui.section.item>
                <flux:switch
                    wire:model.live="memberDeletion"
                    :label="__('Member removal')"
                    :description="__('Allow managers to remove members from this workspace.')"
                />
            </x-ui.section.item>

            <x-ui.section.item>
                <flux:switch
                    wire:model.live="contactDeletion"
                    :label="__('Contact deletion')"
                    :description="__('Allow managers to delete contacts from this workspace.')"
                />
            </x-ui.section.item>

            @if ($isRootTenant)
                <x-ui.section.item>
                    <flux:switch
                        wire:model.live="registration"
                        :label="__('Public registration')"
                        :description="__('Allow visitors to create an account and a tenant organization.')"
                    />
                </x-ui.section.item>
            @endif
        </x-ui.section>

    </x-org-settings.layout>
</section>
