<x-page :heading="__('Organization settings')" :subheading="__('Manage your organization settings')">
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
                                    wire:click="$dispatch('remove-subsidiary', { organizationId: {{ $organization->id }} })"
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

    <livewire:org-settings.subsidiaries.remove-subsidiary />
</x-page>
