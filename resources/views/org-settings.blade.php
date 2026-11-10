<x-layouts::app :title="__('Organization settings')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
            <flux:subheading>{{ __('Read-only organization details.') }}</flux:subheading>
        </div>

        <flux:separator variant="subtle" />

        <flux:card class="max-w-lg space-y-4">
            <div>
                <flux:text>{{ __('Name') }}</flux:text>
                <flux:heading>{{ $organization->name }}</flux:heading>
            </div>

            <div>
                <flux:text>{{ __('Primary contact') }}</flux:text>
                <flux:heading>{{ $organization->primaryContact?->name ?? '—' }}</flux:heading>
            </div>
        </flux:card>
    </div>
</x-layouts::app>
