<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:avatar
                size="lg"
                :initials="$stakeholder->initials()"
                :src="$isOrganization ? $stakeholder->logo_url : $stakeholder->photo_url"
            />

            <div class="relative w-full">
                <flux:heading size="xl" level="1">
                    {{ $stakeholder->name }}
                    <flux:badge color="zinc">{{ $isOrganization ? __('Organization') : __('Individual') }}</flux:badge>

                    @if ($stakeholder->trashed())
                        <flux:badge color="red">{{ __('Deleted') }}</flux:badge>
                    @endif
                </flux:heading>

                <flux:subheading>
                    {{ __('Registered at') }}
                    <x-local-time :date="$stakeholder->created_at" />
                </flux:subheading>
            </div>
        </div>
    </div>

    @if ($stakeholder->trashed())
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('This organization has been deleted.')">
            <flux:callout.text>
                {{ __('You can still see your relationship history, but the organization no longer exists.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:separator variant="subtle" />

    <flux:card class="max-w-lg space-y-4">
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

        @php
            $statusColor = match ($contact->status) {
                \App\Enums\RelationshipStatus::Active => 'green',
                \App\Enums\RelationshipStatus::Scheduled => 'amber',
                \App\Enums\RelationshipStatus::Expired => 'zinc',
            };
        @endphp

        <div>
            <flux:text>{{ __('Status') }}</flux:text>
            <flux:badge size="sm" :color="$statusColor">
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
    </flux:card>

    <x-relationship-history :periods="$this->history" />

    @if ($isOrganization && ! $stakeholder->trashed())
        <flux:card class="max-w-lg space-y-4">
            <flux:heading>{{ __('Contact person') }}</flux:heading>
            <flux:separator variant="subtle" />

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
        </flux:card>
    @endif
</div>
