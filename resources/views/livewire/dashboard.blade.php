@if ($organization === null)
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex flex-1 items-center justify-center">
            <h1 class="mx-auto max-w-xl text-balance text-center text-4xl font-extralight text-zinc-400 dark:text-zinc-500">
                {{ __("You're not part of an organization yet.") }}
            </h1>
        </div>
    </div>
@else
    <x-page :heading="__('Home')">
        <x-slot:actions>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">
                <x-ui.date-picker
                    wire:model.live="range"
                    mode="range"
                    :label="__('Date range')"
                    label:sr-only
                    with-presets
                    start-day="0"
                    :today="$today"
                    :timezone="$timezone"
                    class="w-full sm:w-60"
                />
            </div>
        </x-slot:actions>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('New members') }}</flux:text>
                    <flux:icon name="users" class="size-5 text-zinc-400" />
                </div>

                <flux:heading size="2xl">{{ $this->newMembers }}</flux:heading>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('New contacts') }}</flux:text>
                    <flux:icon name="user-group" class="size-5 text-zinc-400" />
                </div>

                <flux:heading size="2xl">{{ $this->newContacts }}</flux:heading>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Expiring soon') }}</flux:text>
                    <flux:icon name="clock" class="size-5 text-zinc-400" />
                </div>

                <flux:heading size="2xl">{{ $this->expiringCount }}</flux:heading>
            </flux:card>

            <flux:card class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Unread notifications') }}</flux:text>
                    <flux:icon name="bell" class="size-5 text-zinc-400" />
                </div>

                <flux:heading size="2xl">
                    <a
                        href="{{ route('notifications.index') }}"
                        wire:navigate
                        class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                    >
                        {{ $this->unreadNotifications }}
                    </a>
                </flux:heading>
            </flux:card>
        </div>

        <div class="grid gap-4 lg:grid-cols-8">
            <flux:card class="min-h-96 space-y-4 lg:col-span-5">
                <flux:heading size="lg">{{ __('Expiring soon') }}</flux:heading>
                <flux:separator variant="subtle" />

                @forelse ($this->expiringContacts as $contact)
                    @php($stakeholder = $contact->stakeholder)

                    <div wire:key="expiring-{{ $contact->id }}" class="flex items-center justify-between gap-4">
                        @php($reachable = $stakeholder !== null && (! $stakeholder->trashed() || $stakeholder instanceof \App\Models\Organization))

                        <div class="min-w-0">
                            @if ($reachable)
                                <a
                                    href="{{ route('contacts.view', ['stakeholder' => $stakeholder]) }}"
                                    wire:navigate
                                    class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                                >
                                    <flux:heading>{{ $stakeholder->name }}</flux:heading>
                                </a>
                            @else
                                <flux:heading>{{ $stakeholder?->name ?? '—' }}</flux:heading>
                            @endif

                            <flux:text class="text-xs">
                                {{ $contact->type?->label() ?? '—' }}
                                @if (filled($contact->code))
                                    · {{ $contact->code }}
                                @endif
                            </flux:text>
                        </div>

                        <flux:badge size="sm" color="amber">
                            {{ $contact->finish_date?->format('Y-m-d') ?? '—' }}
                        </flux:badge>
                    </div>
                @empty
                    <flux:text>{{ __('Nothing here yet.') }}</flux:text>
                @endforelse
            </flux:card>

            <flux:card class="min-h-96 space-y-4 lg:col-span-3">
                <flux:heading size="lg">{{ __('Recent members') }}</flux:heading>
                <flux:separator variant="subtle" />

                @forelse ($this->recentMembers as $member)
                    <div wire:key="recent-member-{{ $member->id }}" class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:avatar size="sm" :initials="$member->initials()" :src="$member->photo_url" />

                            <div class="min-w-0">
                                <a
                                    href="{{ route('members.show', $member) }}"
                                    wire:navigate
                                    class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                                >
                                    <flux:heading class="truncate">{{ $member->name }}</flux:heading>
                                </a>

                                <flux:text class="text-xs">{{ $member->pivot->role?->label() ?? '—' }}</flux:text>
                            </div>
                        </div>

                        <flux:text class="text-xs whitespace-nowrap">
                            {{ $member->pivot->start_date?->format('Y-m-d') ?? '—' }}
                        </flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('Nothing here yet.') }}</flux:text>
                @endforelse
            </flux:card>
        </div>
    </x-page>
@endif
