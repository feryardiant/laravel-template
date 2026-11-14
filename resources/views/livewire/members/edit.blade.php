<div>
    <flux:modal
        wire:model.self="showModal"
        flyout
        position="right"
        variant="floating"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'edit-member-heading')"
    >
        <form wire:submit="update" class="flex min-h-[calc(100dvh-5rem)] flex-col">
            <div class="space-y-6">
                <div>
                    <flux:heading id="edit-member-heading" size="lg">{{ __('Edit member') }}</flux:heading>
                    <flux:subheading>{{ $person?->name }}</flux:subheading>
                </div>

                @unless ($this->canEditProfile)
                    <flux:callout variant="secondary" icon="lock-closed">
                        <flux:callout.text>
                            {{ $person?->ownerOrganization !== null
                                ? __('Their profile is managed by :organization.', ['organization' => $person->ownerOrganization->name])
                                : __('This person controls their profile.') }}
                            {{ __('You can still change their role.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endunless

                @if ($this->canEditProfile)
                    <x-ui.file-upload wire:model="photo" accept="image/*" :label="__('Member Photo')">
                        <x-ui.file-upload.avatar :url="$photo ?? $this->photoPreviewUrl" shape="rounded" remove="clearPhoto" />
                    </x-ui.file-upload>

                    <flux:input
                        wire:model="name"
                        type="text"
                        :label="__('Name')"
                        required
                        autofocus
                        autocomplete="name"
                    />
                @else
                    <div class="flex items-center gap-3">
                        <flux:avatar :initials="$person?->initials()" :src="$this->photoPreviewUrl" />

                        <div>
                            <flux:text>{{ __('Name') }}</flux:text>
                            <flux:heading>{{ $person?->name }}</flux:heading>
                        </div>
                    </div>
                @endif

                <flux:radio.group wire:model="role" :label="__('Role')" class="flex flex-wrap gap-x-6">
                    @foreach ($this->roles as $roleOption)
                        <flux:radio :value="$roleOption->value" :label="$roleOption->label()" />
                    @endforeach

                    <flux:error name="role" />
                </flux:radio.group>

                @if ($this->genderEnabled)
                    <flux:radio.group
                        wire:model="gender"
                        :label="__('Gender')"
                        class="flex flex-wrap gap-x-6"
                        @disabled(! $this->genderEditable)
                    >
                        @foreach (\App\Enums\Gender::cases() as $genderOption)
                            <flux:radio :value="$genderOption->value" :label="$genderOption->label()" />
                        @endforeach
                    </flux:radio.group>

                    @unless ($this->genderEditable)
                        <flux:text class="text-xs">
                            {{ __('This field is set once and can no longer be changed.') }}
                        </flux:text>
                    @endunless
                @endif

                @if ($this->canEditProfile)
                    @if ($hasCredential)
                        <flux:input
                            wire:model="email"
                            type="email"
                            :label="__('Email')"
                            :description="__('Changing the email address sends a new verification email.')"
                            required
                            autocomplete="email"
                        />
                    @else
                        <flux:checkbox
                            wire:model.live="createCredential"
                            :label="__('Create login credential')"
                            :description="__('An invitation email will be sent so they can set their password.')"
                        />

                        @if ($createCredential)
                            <flux:input
                                wire:model="email"
                                type="email"
                                :label="__('Email')"
                                required
                                autocomplete="email"
                            />
                        @endif
                    @endif
                @else
                    <div>
                        <flux:text>{{ __('Email') }}</flux:text>
                        <flux:heading>{{ $person?->user?->email ?? __('No login credential') }}</flux:heading>
                    </div>
                @endif
            </div>

            <div class="mt-auto flex items-center justify-between pt-6">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
