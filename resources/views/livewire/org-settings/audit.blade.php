<x-page :heading="__('Organization settings')" :subheading="__('Manage your organization settings')">
    <x-org-settings.layout
        :heading="__('Audit log')"
        :subheading="__('Every recorded change in this organization')"
        width="max-w-none"
    >
        <x-slot:actions class="w-full flex-col sm:w-auto sm:flex-row sm:items-end">
            <x-ui.select
                wire:model.live="actor"
                :label="__('Actor')"
                :placeholder="__('All actors')"
                clearable
                class="w-full sm:w-46"
            >
                @foreach ($this->actors as $member)
                    @php($isCurrent = $member->user_id === auth()->id())

                    <x-ui.select.option
                        :value="$member->user_id"
                        :label="$isCurrent ? $member->name.' ('.__('You').')' : $member->name"
                        :keywords="$member->user?->email"
                    >
                        <span class="inline-flex items-center gap-2">
                            {{ $member->name }}

                            @if ($isCurrent)
                                <flux:badge size="sm" color="zinc">{{ __('You') }}</flux:badge>
                            @endif
                        </span>
                    </x-ui.select.option>
                @endforeach
            </x-ui.select>

            <x-ui.select
                wire:model.live="event"
                :label="__('Event')"
                :placeholder="__('All events')"
                clearable
                class="w-full sm:w-46"
            >
                @foreach ($events as $case)
                    <x-ui.select.option :value="$case->value">{{ $case->label() }}</x-ui.select.option>
                @endforeach
            </x-ui.select>

            <x-ui.date-picker
                wire:model.live="range"
                mode="range"
                :label="__('Date range')"
                with-presets
                start-day="0"
                :today="$today"
                :timezone="$timezone"
                class="w-full sm:w-60"
            />
        </x-slot:actions>

        <flux:table :paginate="$this->logs">
            <flux:table.columns>
                <flux:table.column class="w-1/12">{{ __('Event') }}</flux:table.column>
                <flux:table.column class="w-4/12">{{ __('Subject') }}</flux:table.column>
                <flux:table.column class="w-5/12">{{ __('Details') }}</flux:table.column>
                <flux:table.column class="w-1/12">{{ __('Actor') }}</flux:table.column>
                <flux:table.column class="w-1/12">{{ __('When') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->logs as $log)
                    @php($details = $log->changes?->details() ?? [])

                    <flux:table.row :key="$log->id">
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$log->event->badgeColor()">{{ $log->event->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ $log->changes?->subjectLabel() ?? $log->event->label() }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">
                            {{ $details === [] ? '—' : implode(', ', $details) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="relative inline-flex items-center gap-2">
                                @if ($log->actor)
                                    <flux:avatar size="xs" :name="$log->actor->name" :src="$log->actor->photo_url" />
                                @endif
                                <span class="truncate">{{ $log->actor?->name ?? __('System') }}</span>
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <x-local-time :date="$log->created_at" format="d M Y H:i" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <flux:text>{{ __('No audit entries yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-org-settings.layout>
</x-page>
