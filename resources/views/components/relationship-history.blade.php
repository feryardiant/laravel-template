@props(['periods'])

<flux:card body="divided">
    <flux:card.header>
        <flux:heading>{{ __('History') }}</flux:heading>
    </flux:card.header>

    <flux:card.body class="max-h-96 overflow-y-auto">
        @if ($periods->isEmpty())
            <flux:text class="text-sm">{{ __('No histories yet.') }}</flux:text>
        @else
            <x-ui.timeline>
                @foreach ($periods as $period)
                    <x-ui.timeline.item wire:key="period-{{ $period->id }}" :status="$loop->first ? 'current' : 'complete'">
                        <flux:heading>
                            {{ $period->start_date->format('Y-m-d') }}
                            —
                            {{ $period->finish_date?->format('Y-m-d') ?? __('Present') }}
                        </flux:heading>

                        <flux:text size="sm">
                            {{ $period->type->label() }}

                            @if ($period->role !== null)
                                · {{ $period->role->label() }}
                            @endif

                            @if ($period->code !== null)
                                · {{ $period->code }}
                            @endif

                            @if ($period->reason !== null)
                                · {{ $period->reason->label() }}
                            @endif

                            @if ($period->actor !== null)
                                · {{ $period->actor->name }}
                            @endif
                        </flux:text>
                    </x-ui.timeline.item>
                @endforeach
            </x-ui.timeline>
        @endif
    </flux:card.body>
</flux:card>
