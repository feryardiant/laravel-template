@props(['periods'])

<flux:card class="max-w-2xl space-y-4">
    <flux:heading>{{ __('History') }}</flux:heading>
    <flux:separator variant="subtle" />

    @forelse ($periods as $period)
        <div wire:key="period-{{ $period->id }}" class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <flux:heading>
                    {{ $period->start_date->format('Y-m-d') }}
                    —
                    {{ $period->finish_date?->format('Y-m-d') ?? __('Present') }}
                </flux:heading>

                <flux:text class="text-xs">
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
            </div>

            @if ($period->isOpen())
                <flux:badge size="sm" color="green">{{ __('Current') }}</flux:badge>
            @endif
        </div>
    @empty
        <flux:text>{{ __('No history yet.') }}</flux:text>
    @endforelse
</flux:card>
