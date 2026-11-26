<x-page :heading="__('Organization settings')" :subheading="__('Manage your organization settings')">
    <x-org-settings.layout :heading="__('Features')" :subheading="__('Control platform and workspace features')">
        <x-ui.section>
            @foreach ($this->fields as $feature)
                <x-ui.section.item>
                    <flux:switch
                        wire:model.live="features['{{ $feature->value }}']"
                        :label="$feature->label()"
                        :description="$feature->description()"
                    />
                </x-ui.section.item>
            @endforeach
        </x-ui.section>
    </x-org-settings.layout>
</x-page>
