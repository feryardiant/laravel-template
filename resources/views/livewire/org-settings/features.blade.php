<x-page :heading="__('Organization settings')" :subheading="__('Manage your organization settings')">
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
                    wire:model.live="profileSelfEdit"
                    :label="__('Profile self-edit')"
                    :description="__('Let org-owned people edit their own name, email, and photo.')"
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
</x-page>
