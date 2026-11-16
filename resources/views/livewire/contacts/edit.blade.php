<x-modal
    flyout
    position="right"
    variant="floating"
    wire:model.self="showModal"
    :title="$contactId === null ? __('Add contact') : __('Edit contact')"
    :subtitle="$contactId === null ? __('Add a contact for this organization.') : $name"
    class="flex flex-col"
>
    <form wire:submit="save" class="-mb-6 flex flex-1 flex-col gap-6">
        <div class="space-y-6">
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

            @unless ($this->sharedFieldsLocked)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading>{{ __('Address') }}</flux:heading>
                        <flux:separator variant="subtle" class="mt-2" />
                    </div>

                    <livewire:address wire:model="address" />

                    @foreach (['line', 'rt', 'rw', 'postal_code', 'province_code', 'regency_code', 'district_code', 'village_code'] as $field)
                        <flux:error :name="'address.'.$field" />
                    @endforeach
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

                    <flux:input
                        wire:model="contact_name"
                        type="text"
                        :label="__('Name')"
                        :disabled="$this->sharedFieldsLocked"
                    />

                    @if ($genderEnabled)
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
                    @endif

                    <flux:textarea
                        wire:model="contact_summary"
                        :label="__('Summary')"
                        :disabled="$this->sharedFieldsLocked"
                    />
                </flux:card>
            @endif
        </div>

        <div class="sticky bottom-0 -mx-8 mt-auto flex items-center justify-between bg-white px-8 before:absolute before:inset-x-0 before:top-full before:h-8 before:bg-white before:content-[''] dark:bg-zinc-800 dark:before:bg-zinc-800">
            <flux:modal.close>
                <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit">
                {{ $contactId === null ? __('Add') : __('Save') }}
            </flux:button>
        </div>
    </form>
</x-modal>
