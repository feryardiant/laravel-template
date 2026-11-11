<div>
    <flux:modal
        wire:model.self="showModal"
        flyout
        position="right"
        variant="floating"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'contact-form-heading')"
    >
        <form wire:submit="save" class="flex min-h-[calc(100dvh-5rem)] flex-col">
            <div class="space-y-6">
                <div>
                    <flux:heading id="contact-form-heading" size="lg">
                        {{ $contactId === null ? __('Add contact') : __('Edit contact') }}
                    </flux:heading>
                    <flux:subheading>
                        {{ $contactId === null ? __('Add a contact for this organization.') : $name }}
                    </flux:subheading>
                </div>

                @if ($this->sharedFieldsLocked)
                    <flux:field>
                        <flux:label>{{ __('Photo') }}</flux:label>
                        <div>
                            <flux:avatar size="lg" :src="$this->photoPreviewUrl" />
                        </div>
                        <flux:description>{{ __('Shared contact details are read-only.') }}</flux:description>
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
                    <flux:field>
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model="name" type="text" :disabled="$this->sharedFieldsLocked" required autocomplete="off" />
                        <flux:error name="name" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="code" type="text" autocomplete="off" />
                    <flux:error name="code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Summary') }}</flux:label>
                    <flux:textarea wire:model="summary" :disabled="$this->sharedFieldsLocked" />
                    <flux:error name="summary" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Role') }}</flux:label>
                    <flux:select wire:model="role">
                        @foreach ($this->roles as $roleOption)
                            <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="role" />
                </flux:field>

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

                @if (! $this->isOrganizationType)
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
                    wire:model.live="start_date"
                    :label="__('Start date')"
                    with-today
                    clearable
                />

                <x-ui.date-picker
                    wire:model="finish_date"
                    :label="__('Finish date')"
                    :min="$start_date"
                    wire:key="finish-date-{{ $start_date }}"
                    clearable
                />

                @if ($this->isOrganizationType)
                    <flux:card class="space-y-4">
                        <div>
                            <flux:heading>{{ __('Contact person') }}</flux:heading>
                            <flux:separator variant="subtle" class="mt-2" />
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Name') }}</flux:label>
                            <flux:input wire:model="contact_name" type="text" :disabled="$this->sharedFieldsLocked" />
                            <flux:error name="contact_name" />
                        </flux:field>

                        <flux:radio.group
                            wire:model="contact_gender"
                            :label="__('Gender')"
                            class="flex flex-wrap gap-x-6"
                            :disabled="$this->sharedFieldsLocked"
                        >
                            @foreach (\App\Enums\Gender::cases() as $gender)
                                <flux:radio :value="$gender->value" :label="$gender->label()" />
                            @endforeach

                            <flux:error name="contact_gender" />
                        </flux:radio.group>

                        <flux:field>
                            <flux:label>{{ __('Summary') }}</flux:label>
                            <flux:textarea wire:model="contact_summary" :disabled="$this->sharedFieldsLocked" />
                            <flux:error name="contact_summary" />
                        </flux:field>
                    </flux:card>
                @endif
            </div>

            <div class="mt-auto flex items-center justify-between pt-6">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ $contactId === null ? __('Add') : __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
