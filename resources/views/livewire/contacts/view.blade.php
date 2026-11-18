<x-page>
    <x-slot:leading>
        <flux:avatar
            size="lg"
            :initials="$stakeholder->initials()"
            :src="$isOrganization ? $stakeholder->logo_url : $stakeholder->photo_url"
        />
    </x-slot:leading>

    <x-slot:heading>
        {{ $stakeholder->name }}
        <flux:badge color="zinc">{{ $isOrganization ? __('Organization') : __('Individual') }}</flux:badge>

        @if ($stakeholder->trashed())
            <flux:badge color="red">{{ __('Deleted') }}</flux:badge>
        @endif
    </x-slot:heading>

    <x-slot:subheading>
        <flux:subheading>
            {{ __('Registered at') }}
            <x-local-time :date="$stakeholder->created_at" />
        </flux:subheading>
    </x-slot:subheading>

    <div class="flex flex-col gap-6 md:flex-row">
        <div class="flex flex-col gap-4 w-full md:w-5/8">
            @if ($stakeholder->trashed())
                <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('This organization has been deleted.')">
                    <flux:callout.text>
                        {{ __('You can still see your relationship history, but the organization no longer exists.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="flex flex-col gap-4 w-full md:w-3/8">
            <flux:card class="max-w-lg">
                <flux:card.body class="flex flex-col gap-4">
                    <div>
                        <flux:text>{{ __('Type') }}</flux:text>
                        <flux:heading>{{ $isOrganization ? __('Organization') : __('Individual') }}</flux:heading>
                    </div>

                    @if (! $isOrganization && $showGender)
                        <div>
                            <flux:text>{{ __('Gender') }}</flux:text>
                            <flux:heading>{{ $stakeholder->gender?->label() ?? '—' }}</flux:heading>
                        </div>
                    @endif

                    <div>
                        <flux:text>{{ __('Code') }}</flux:text>
                        <flux:heading>{{ $contact->code ?: '—' }}</flux:heading>
                    </div>

                    <div>
                        <flux:text>{{ __('Role') }}</flux:text>
                        <flux:heading>{{ $contact->type?->label() ?? '—' }}</flux:heading>
                    </div>

                    <div>
                        <flux:text>{{ __('Status') }}</flux:text>
                        <flux:badge size="sm" :color="$contact->status->badgeColor()">
                            {{ $contact->status->label() }}
                        </flux:badge>
                    </div>

                    <div>
                        <flux:text>{{ __('Start date') }}</flux:text>
                        <flux:heading>{{ $contact->start_date?->format('Y-m-d') ?? '—' }}</flux:heading>
                    </div>

                    <div>
                        <flux:text>{{ __('Finish date') }}</flux:text>
                        <flux:heading>{{ $contact->finish_date?->format('Y-m-d') ?? '—' }}</flux:heading>
                    </div>

                    <div>
                        <flux:text>{{ __('Summary') }}</flux:text>
                        <flux:heading>{{ $stakeholder->summary ?: '—' }}</flux:heading>
                    </div>
                </flux:card.body>
            </flux:card>

            @php($address = $isOrganization ? $stakeholder->address()->first() : $stakeholder->addresses()->first())

            @if ($address !== null)
                <x-address-summary :address="$address" />
            @endif

            <x-relationship-history :periods="$this->history" />

            @if ($isOrganization && ! $stakeholder->trashed())
                <flux:card body="divided" class="max-w-lg">
                    <flux:card.header>
                        <flux:heading>{{ __('Contact person') }}</flux:heading>
                    </flux:card.header>

                    <flux:card.body class="flex flex-col gap-4">
                        <div>
                            <flux:text>{{ __('Name') }}</flux:text>
                            <flux:heading>{{ $contactPerson?->name ?? '—' }}</flux:heading>
                        </div>

                        @if ($showGender)
                            <div>
                                <flux:text>{{ __('Gender') }}</flux:text>
                                <flux:heading>{{ $contactPerson?->gender?->label() ?? '—' }}</flux:heading>
                            </div>
                        @endif

                        <div>
                            <flux:text>{{ __('Summary') }}</flux:text>
                            <flux:heading>{{ $contactPerson?->summary ?: '—' }}</flux:heading>
                        </div>
                    </flux:card.body>
                </flux:card>
            @endif
        </div>
    </div>
</x-page>
