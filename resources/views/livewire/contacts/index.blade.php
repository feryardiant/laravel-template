<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Contacts') }}</flux:heading>
            <flux:subheading>
                {{ $organization === null
                    ? __('You are not a member of any organization.')
                    : __('Contacts of :organization', ['organization' => $organization->name]) }}
            </flux:subheading>
        </div>

        @if ($canManage)
            <div class="hidden items-center gap-2 sm:flex">
                <flux:button variant="primary" icon="plus" wire:click="createContact">
                    {{ __('Add contact') }}
                </flux:button>

                @if ($canDeleteAny && $selected !== [])
                    <flux:dropdown align="end" class="order-first">
                        <flux:button icon:trailing="chevron-down">
                            {{ __('Action') }}
                        </flux:button>

                        <flux:menu>
                            <flux:menu.item
                                variant="danger"
                                icon="trash"
                                wire:click="confirmDeleteSelected"
                            >
                                {{ __('Delete selected contacts') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>

            <flux:dropdown align="end" class="sm:hidden">
                <flux:button
                    icon="ellipsis-horizontal"
                    square
                    :aria-label="__('Actions')"
                />

                <flux:menu>
                    <flux:menu.item icon="plus" wire:click="createContact">
                        {{ __('Add contact') }}
                    </flux:menu.item>

                    @if ($canDeleteAny && $selected !== [])
                        <flux:menu.separator />

                        <flux:menu.item
                            variant="danger"
                            icon="trash"
                            wire:click="confirmDeleteSelected"
                        >
                            {{ __('Delete selected contacts') }}
                        </flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    <flux:separator variant="subtle" />

    @if ($organization === null)
        <flux:card class="max-w-lg">
            <flux:heading>{{ __('No organization') }}</flux:heading>
            <flux:text class="mt-2">{{ __('You are not a member of any organization.') }}</flux:text>
        </flux:card>
    @else
        <div class="flex justify-end">
            <flux:input
                type="search"
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search contacts...')"
                :aria-label="__('Search contacts')"
                class="sm:max-w-xs"
            />
        </div>

        @php($selectedIds = array_map('intval', $selected))

        <flux:checkbox.group
            wire:key="contacts-{{ $contacts->currentPage() }}-{{ $search }}-{{ $selectionKey }}"
            wire:model.live="selected"
        >
            <flux:table :paginate="$contacts">
                <flux:table.columns>
                    <flux:table.column class="w-7/12">
                        <span class="inline-flex items-center gap-2">
                            @if ($canDeleteAny)
                                <label class="relative -m-1.5 inline-flex shrink-0 cursor-pointer items-center justify-center p-1.5">
                                    <input
                                        type="checkbox"
                                        wire:change="toggleSelectAll"
                                        @checked($allSelected)
                                        x-init="const el = $el; const syncIndeterminate = () => el.indeterminate = $wire.someSelected; syncIndeterminate(); $wire.$watch('someSelected', syncIndeterminate)"
                                        aria-label="{{ __('Select all contacts') }}"
                                        class="peer size-[1.125rem] cursor-pointer appearance-none rounded-[.3rem] border border-zinc-300 bg-white shadow-xs outline-offset-2 checked:border-zinc-800 checked:bg-zinc-800 indeterminate:border-zinc-800 indeterminate:bg-zinc-800 dark:border-white/10 dark:bg-white/10 dark:checked:border-white dark:checked:bg-white dark:indeterminate:border-white dark:indeterminate:bg-white"
                                    />

                                    <svg class="pointer-events-none absolute hidden size-[.6rem] text-white peer-checked:block dark:text-zinc-800" viewBox="0 0 12 10" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M1 5 4.5 8.5 11 1" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>

                                    <svg class="pointer-events-none absolute hidden h-0.5 w-[.55rem] rounded-full bg-white peer-indeterminate:block dark:bg-zinc-800" viewBox="0 0 10 2" fill="currentColor" aria-hidden="true">
                                        <rect width="10" height="2" rx="1" />
                                    </svg>
                                </label>
                            @endif

                            {{ __('Name') }}
                        </span>
                    </flux:table.column>
                    <flux:table.column class="w-2/12">{{ __('Contact person') }}</flux:table.column>
                    <flux:table.column align="end" class="w-2/12">{{ __('Registered at') }}</flux:table.column>
                    <flux:table.column align="center" class="w-1/12">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($contacts as $contact)
                        @php($isSelected = in_array($contact->id, $selectedIds, true))
                        @php($stakeholder = $contact->stakeholder)
                        @php($isOrganization = $contact->isOrganization())
                        @php($rowClick = $canManage
                            ? "if (! window.getSelection()?.toString() && ! \$event.target.closest('a, button, input, ui-checkbox, ui-dropdown')) \$wire.dispatch('edit-contact', { contactId: {$contact->id} })"
                            : "if (! window.getSelection()?.toString() && ! \$event.target.closest('a, button, input, ui-checkbox, ui-dropdown')) window.Livewire.navigate('".route('contacts.view', ['stakeholder' => $stakeholder])."')")

                        <flux:table.row
                            :key="$contact->id"
                            class="group/row cursor-pointer hover:bg-zinc-100/60 dark:hover:bg-zinc-700/30"
                            x-on:click="{{ $rowClick }}"
                        >
                            <flux:table.cell class="w-7/12">
                                <span class="relative inline-flex items-center gap-2">
                                    <a
                                        href="{{ route('contacts.view', ['stakeholder' => $stakeholder]) }}"
                                        wire:navigate
                                        class="inline-flex items-center gap-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                                    >
                                        <span
                                            @class([
                                                'relative inline-flex ml-1 size-8 shrink-0 items-center justify-center transition-opacity',
                                                'opacity-0' => $isSelected,
                                                'group-hover/row:opacity-0 group-focus-within/row:opacity-0 [@media(hover:none)]:opacity-0' => ! $isSelected && $canDeleteAny,
                                            ])
                                            aria-hidden="true"
                                        >
                                            <flux:avatar
                                                size="sm"
                                                :initials="$stakeholder->initials()"
                                                :src="$isOrganization ? $stakeholder->logo_url : $stakeholder->photo_url"
                                            />
                                        </span>

                                        {{ $stakeholder->name }}
                                    </a>

                                    <flux:badge size="sm" color="zinc">
                                        {{ $isOrganization ? __('Organization') : __('Individual') }}
                                    </flux:badge>

                                    @if ($canDeleteAny)
                                        <span
                                            class="absolute left-0 top-1/2 z-10 flex size-10 -translate-y-1/2 items-center justify-center"
                                            x-on:click="if (! $event.target.closest('ui-checkbox')) { $event.stopPropagation(); $el.querySelector('ui-checkbox').click() }"
                                        >
                                            <flux:checkbox
                                                value="{{ $contact->id }}"
                                                :aria-label="__('Select :name', ['name' => $stakeholder->name])"
                                                @class([
                                                    'transition-opacity',
                                                    'pointer-events-none opacity-0 group-hover/row:pointer-events-auto group-hover/row:opacity-100 group-focus-within/row:pointer-events-auto group-focus-within/row:opacity-100 [@media(hover:none)]:pointer-events-auto [@media(hover:none)]:opacity-100' => ! $isSelected,
                                                ])
                                            />
                                        </span>
                                    @endif
                                </span>
                            </flux:table.cell>

                            <flux:table.cell class="w-2/12">
                                @if ($isOrganization)
                                    {{ $stakeholder->primaryContact?->name ?? '—' }}
                                @else
                                    <flux:text>—</flux:text>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end" class="w-2/12 whitespace-nowrap">
                                <x-local-time :date="$stakeholder->created_at" />
                            </flux:table.cell>

                            <flux:table.cell align="center" class="w-1/12">
                                <flux:dropdown align="end">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        square
                                        icon="ellipsis-horizontal"
                                        :disabled="$isSelected"
                                        :aria-label="__('Actions for :name', ['name' => $stakeholder->name])"
                                    />

                                    <flux:menu>
                                        @if ($canManage)
                                            <flux:menu.item
                                                icon="pencil-square"
                                                wire:click="editContact({{ $contact->id }})"
                                            >
                                                {{ __('Edit contact') }}
                                            </flux:menu.item>
                                        @endif

                                        <flux:menu.item
                                            icon="eye"
                                            :href="route('contacts.view', ['stakeholder' => $stakeholder])"
                                            wire:navigate
                                        >
                                            {{ __('View contact') }}
                                        </flux:menu.item>

                                        @if ($canManage)
                                            <flux:menu.separator />

                                            <flux:menu.item
                                                variant="danger"
                                                icon="trash"
                                                wire:click="confirmDelete({{ $contact->id }}, @js($stakeholder->name))"
                                            >
                                                {{ __('Delete contact') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:checkbox.group>
    @endif

    <flux:modal
        wire:model.self="showDeleteModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'delete-contact-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="delete-contact-heading" size="lg">{{ __('Delete contact') }}</flux:heading>
                <flux:subheading>
                    {{ __('Delete :name from your contacts?', ['name' => $deletingContactName ?? __('this contact')]) }}
                </flux:subheading>
            </div>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteConfirmed">
                    {{ __('Delete contact') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        wire:model.self="showBatchDeleteModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'delete-selected-contacts-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="delete-selected-contacts-heading" size="lg">{{ __('Delete contacts') }}</flux:heading>
                <flux:subheading>
                    {{ __('Delete :count selected contacts?', ['count' => count($selected)]) }}
                </flux:subheading>
            </div>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteSelectedConfirmed">
                    {{ __('Delete contacts') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <livewire:contacts.edit />
</div>
