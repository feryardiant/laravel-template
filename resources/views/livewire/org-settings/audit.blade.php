<x-page :heading="__('Organization settings')" :subheading="__('Manage your organization settings')">
    <x-org-settings.layout
        :heading="__('Audit log')"
        :subheading="__('Every recorded change in this organization')"
        width="max-w-none"
    >
        <x-slot:actions>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">
                <flux:select wire:model.live="event" :label="__('Event')" class="w-full sm:w-40">
                    <flux:select.option value="">{{ __('All events') }}</flux:select.option>
                    @foreach ($events as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

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
            </div>
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
                        <flux:table.cell class="whitespace-normal">{{ $log->changes }}</flux:table.cell>
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
