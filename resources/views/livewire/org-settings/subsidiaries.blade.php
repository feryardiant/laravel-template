<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Manage your organization settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading level="2" class="sr-only">{{ __('Subsidiaries') }}</flux:heading>

    <x-org-settings.layout
        :heading="__('Subsidiaries')"
        :subheading="__('Manage the organizations that belong to the root tenant')"
        width="max-w-none"
    >
        <x-slot:actions>
            @if ($this->canManage)
                <flux:button icon="plus" data-test="new-subsidiary" wire:click="$dispatch('create-organization')">
                    {{ __('New subsidiary') }}
                </flux:button>
            @endif
        </x-slot>

        @if ($this->subsidiaries->isEmpty())
            <flux:text>{{ __('This organization has no subsidiaries.') }}</flux:text>
        @else
            <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($this->subsidiaries as $row)
                    @php($organization = $row['organization'])
                    @php($parent = $row['parent'])
                    @php($kind = $row['kind'])

                    <div
                        wire:key="subsidiary-{{ $organization->id }}"
                        class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:avatar
                                :initials="$organization->initials()"
                                :src="$organization->logo_url"
                                :tooltip="$row['depth'] > 0 ? __(':kind of :name', ['kind' => $kind?->label() ?? __('Subsidiary'), 'name' => $parent->name]) : null"
                            >
                                @if ($row['depth'] > 0)
                                    <x-slot:badge>
                                        <flux:avatar
                                            :initials="$parent->initials()"
                                            :src="$parent->logo_url"
                                            class="size-3 text-[6px]"
                                        />
                                    </x-slot:badge>
                                @endif
                            </flux:avatar>

                            <div class="min-w-0">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <flux:heading class="truncate">{{ $organization->name }}</flux:heading>

                                    @if ($kind !== null)
                                        <flux:badge size="sm" color="zinc">{{ $kind->label() }}</flux:badge>
                                    @endif
                                </div>

                                <flux:text class="text-xs" variant="subtle">
                                    <x-local-time :date="$organization->created_at" />
                                </flux:text>

                                @if ($row['depth'] > 0)
                                    <span class="sr-only">{{ __(':kind of :name', ['kind' => $kind?->label() ?? __('Subsidiary'), 'name' => $parent->name]) }}</span>
                                @endif
                            </div>
                        </div>

                        @if ($this->canManage)
                            <div class="flex shrink-0">
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="confirmRemove({{ $organization->id }})"
                                >
                                    {{ __('Remove') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-org-settings.layout>

    <livewire:settings.organizations.create :internal="true" />

    <flux:modal
        wire:model.self="showRemoveModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'remove-subsidiary-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="remove-subsidiary-heading" size="lg">{{ __('Remove subsidiary') }}</flux:heading>
                <flux:subheading>{{ __('Remove :name?', ['name' => $removingName]) }}</flux:subheading>
            </div>

            <flux:text>
                {{ __('This permanently deletes the organization, its members, contacts, addresses and logo. People and login credentials are kept, and the deletion runs in the background.') }}

                @if ($removingCount > 1)
                    {{ __('This will also remove :count nested subsidiaries.', ['count' => $removingCount - 1]) }}
                @endif
            </flux:text>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="remove">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
