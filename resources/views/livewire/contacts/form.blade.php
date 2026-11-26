<x-modal
    flyout
    mode="form"
    position="right"
    variant="floating"
    wire:model.self="showModal"
    wire:submit="save"
    :title="$contactId === null ? __('Add contact') : __('Edit contact')"
    :subtitle="$contactId === null ? __('Add a contact for this organization.') : $name"
    class="flex flex-col"
>
    @if ($this->sharedFieldsLocked)
        <flux:field>
            <flux:label>{{ __('Photo') }}</flux:label>
            <div>
                <flux:avatar size="lg" :src="$this->photoPreviewUrl" />
            </div>
            <flux:description>
                {{ $this->reusing
                    ? __('Shared contact details are read-only.')
                    : __('Shared details are read-only. Email and phone are shared with every organization that knows this contact, and stay editable.') }}
            </flux:description>
        </flux:field>
    @else
        <x-ui.file-upload wire:model="photo" accept="image/*" :label="__('Photo')">
            <x-ui.file-upload.avatar :url="$photo ?? $this->photoPreviewUrl" shape="rounded" remove="clearPhoto" />
        </x-ui.file-upload>
    @endif

    @if ($contactId === null)
        <x-ui.autocomplete
            wire:model.live="name"
            value-model="selectedEntity"
            :label="__('Name')"
            :placeholder="__('Search or enter a new contact...')"
            icon="magnifying-glass"
            :search-url="route('contacts.lookup')"
            :min-length="2"
            :debounce="300"
            clearable
            required
            autofocus
        />
    @else
        <flux:input
            wire:model="name"
            type="text"
            :label="__('Name')"
            :disabled="$this->sharedFieldsLocked"
            required
            autocomplete="off"
        />
    @endif

    <flux:input wire:model="code" type="text" :label="__('Code')" autocomplete="off" />

    <flux:textarea
        wire:model="summary"
        :label="__('Summary')"
        :disabled="$this->sharedFieldsLocked"
    />

    @unless ($this->reusing)
        <flux:input
            wire:model="contactEmail"
            type="email"
            :label="$this->isOrganizationType ? __('Organization email') : __('Email')"
            autocomplete="off"
        />
        <flux:input
            wire:model="contactPhone"
            type="tel"
            :label="$this->isOrganizationType ? __('Organization phone') : __('Phone')"
            autocomplete="off"
        />
    @endunless

    @unless ($this->sharedFieldsLocked)
        <flux:card body="divided">
            <flux:card.header>
                <flux:heading>{{ __('Address') }}</flux:heading>
            </flux:card.header>

            <flux:card.body class="flex flex-col gap-4">
                <livewire:view.address wire:model="address" />

                @foreach (['line', 'rt', 'rw', 'postal_code', 'province_code', 'regency_code', 'district_code', 'village_code'] as $field)
                    <flux:error :name="'address.'.$field" />
                @endforeach
            </flux:card.body>
        </flux:card>
    @endunless

    <flux:select wire:model="role" :label="__('Role')">
        @foreach ($this->roles as $roleOption)
            <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
        @endforeach
    </flux:select>

    <flux:radio.group
        wire:model.live="type"
        :label="__('Type')"
        class="flex flex-wrap gap-x-6"
        :disabled="$contactId !== null || $this->reusing"
    >
        <flux:radio value="individual" :label="__('Individual')" />
        <flux:radio value="organization" :label="__('Organization')" />
        <flux:error name="type" />
    </flux:radio.group>

    @if ($contactId !== null)
        <flux:description>{{ __('The contact type cannot be changed after creation.') }}</flux:description>
    @endif

    @if (! $this->isOrganizationType && $genderEnabled)
        <flux:radio.group
            wire:model="gender"
            :label="__('Gender')"
            class="flex flex-wrap gap-x-6"
            :disabled="$this->sharedFieldsLocked"
        >
            @foreach (\App\Enums\Gender::cases() as $gender)
                <flux:radio :value="$gender->value" :label="$gender->label()" />
            @endforeach

            <flux:error name="gender" />
        </flux:radio.group>
    @endif

    <x-ui.date-picker
        wire:model.live="startDate"
        :label="__('Start date')"
        with-today
        clearable
    />

    <x-ui.date-picker
        wire:model="finishDate"
        :label="__('Finish date')"
        :min="$startDate"
        wire:key="finish-date-{{ $startDate }}"
        clearable
    />

    @if ($this->isOrganizationType)
        <flux:card body="divided">
            <flux:card.header>
                <flux:heading>{{ __('Contact person') }}</flux:heading>
            </flux:card.header>

            <flux:card.body class="flex flex-col gap-4">
                <flux:input
                    wire:model="contactName"
                    type="text"
                    :label="__('Name')"
                    :disabled="$this->sharedFieldsLocked"
                />

                @if ($genderEnabled)
                    <flux:radio.group
                        wire:model="contactGender"
                        :label="__('Gender')"
                        class="flex flex-wrap gap-x-6"
                        :disabled="$this->sharedFieldsLocked"
                    >
                        @foreach (\App\Enums\Gender::cases() as $gender)
                            <flux:radio :value="$gender->value" :label="$gender->label()" />
                        @endforeach

                        <flux:error name="contactGender" />
                    </flux:radio.group>
                @endif

                <flux:textarea
                    wire:model="contactSummary"
                    :label="__('Summary')"
                    :disabled="$this->sharedFieldsLocked"
                />

                @unless ($this->reusing)
                    <flux:input wire:model="contactPersonEmail" type="email" :label="__('Email')" autocomplete="off" />
                    <flux:input wire:model="contactPersonPhone" type="tel" :label="__('Phone')" autocomplete="off" />
                @endunless
            </flux:card.body>
        </flux:card>
    @endif

    <x-slot:actions>
        <flux:button variant="primary" type="submit">
            {{ $contactId === null ? __('Add') : __('Save') }}
        </flux:button>
    </x-slot:actions>
</x-modal>
