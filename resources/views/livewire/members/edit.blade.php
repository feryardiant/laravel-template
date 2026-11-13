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

                <flux:radio.group wire:model="role" :label="__('Role')" class="flex flex-wrap gap-x-6">
                    @foreach ($this->roles as $roleOption)
                        <flux:radio :value="$roleOption->value" :label="$roleOption->label()" />
                    @endforeach

                    <flux:error name="role" />
                </flux:radio.group>

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
