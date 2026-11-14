<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name, profile, and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            @unless ($this->canEditOwnProfile)
                <flux:callout variant="secondary" icon="lock-closed">
                    <flux:callout.text>
                        {{ __('Your name, email, and photo are managed by :organization.', ['organization' => $this->person?->ownerOrganization?->name ?? __('your organization')]) }}
                    </flux:callout.text>
                </flux:callout>
            @endunless

            @if ($this->canEditOwnProfile)
                <x-ui.file-upload wire:model="avatar" accept="image/*" :label="__('User avatar')">
                    <x-ui.file-upload.avatar :url="$avatar ?? $this->avatarPreviewUrl" remove="clearAvatar" />
                </x-ui.file-upload>

                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />
            @else
                <div class="flex items-center gap-3">
                    <flux:avatar :src="$this->avatarPreviewUrl" :initials="auth()->user()->initials()" size="lg" />

                    <div>
                        <flux:text>{{ __('Name') }}</flux:text>
                        <flux:heading>{{ $name }}</flux:heading>
                    </div>
                </div>
            @endif

            <flux:input
                wire:model="handle"
                :label="__('Handle')"
                :description="__('4-15 lowercase letters and numbers.')"
                type="text"
                required
                autocomplete="username"
            />

            <flux:input
                wire:model="email"
                :label="__('Email')"
                type="email"
                :disabled="! $this->canEditOwnProfile"
                :required="$this->canEditOwnProfile"
                autocomplete="email"
            />

            @if ($this->hasUnverifiedEmail)
                <div class="mt-4">
                    <flux:text>
                        {{ __('Your email address is unverified.') }}

                        <flux:link class="cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                            {{ __('Click here to re-send the verification email.') }}
                        </flux:link>
                    </flux:text>
                </div>
            @endif

            <flux:textarea
                wire:model="summary"
                :label="__('Summary')"
                :description="__('A short description that appears on your contact card.')"
                rows="3"
            />

            @if ($this->genderEnabled)
                <div class="space-y-2">
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
                </div>
            @endif

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
