@props(['address'])

<flux:card class="max-w-lg space-y-4">
    <flux:heading>{{ __('Address') }}</flux:heading>
    <flux:separator variant="subtle" />

    <div>
        <flux:text>{{ __('Address line') }}</flux:text>
        <flux:heading>{{ $address->line ?: '—' }}</flux:heading>
    </div>

    @if ($address->rt !== null || $address->rw !== null)
        <div>
            <flux:text>{{ __('RT / RW') }}</flux:text>
            <flux:heading>{{ ($address->rt ?? '—').' / '.($address->rw ?? '—') }}</flux:heading>
        </div>
    @endif

    <div>
        <flux:text>{{ __('Region') }}</flux:text>
        <flux:heading>
            {{ collect([
                $address->village?->name,
                $address->district?->name,
                $address->regency?->name,
                $address->province?->name,
            ])->filter()->implode(', ') ?: '—' }}
        </flux:heading>
    </div>

    <div>
        <flux:text>{{ __('Postal code') }}</flux:text>
        <flux:heading>{{ $address->postal_code ?: '—' }}</flux:heading>
    </div>
</flux:card>
