<div>
    <x-modal
        flyout
        mode="form"
        position="right"
        variant="floating"
        wire:model.self="showModal"
        wire:submit="create"
        :title="$internal ? __('New subsidiary') : __('New organization')"
        :subtitle="$internal ? __('Create an organization under the root tenant.') : __('Create an organization you own and manage.')"
        class="flex flex-col"
    >
        <x-ui.file-upload wire:model="logo" accept="image/*" :label="__('Organization Logo')">
            <x-ui.file-upload.avatar :url="$logo" shape="rounded" remove="clearLogo" />
        </x-ui.file-upload>

        <flux:input
            wire:model="name"
            type="text"
            :label="__('Name')"
            required
            autofocus
            autocomplete="off"
        />

        @if ($this->parentOrganizations->isNotEmpty())
            <x-ui.select
                wire:model.live="parentId"
                :label="__('Parent organization')"
                :description="$internal ? __('Leave empty to add it directly under the root.') : __('Selecting a parent makes this organization a subsidiary.')"
                :placeholder="$internal ? __('Root tenant') : __('No parent')"
                clearable
            >
                @foreach ($this->parentOrganizations as $organization)
                    <x-ui.select.option :value="$organization->getKey()">
                        {{ $organization->name }}
                    </x-ui.select.option>
                @endforeach
            </x-ui.select>
        @endif

        @if ($parentId !== null || $internal)
            <x-ui.select
                wire:model="kind"
                :label="__('Organization type')"
                :description="__('How this organization relates to its parent.')"
            >
                @foreach ($this->kinds as $kind)
                    <x-ui.select.option :value="$kind->value">{{ $kind->label() }}</x-ui.select.option>
                @endforeach
            </x-ui.select>
        @endif

        <x-slot:actions>
            <flux:button variant="primary" type="submit">
                {{ $internal ? __('Create subsidiary') : __('Create organization') }}
            </flux:button>
        </x-slot:actions>
    </x-modal>
</div>
