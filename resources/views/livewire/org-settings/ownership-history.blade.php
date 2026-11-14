<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Manage your organization settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading level="2" class="sr-only">{{ __('Ownership history') }}</flux:heading>

    <x-org-settings.layout
        :heading="__('Ownership history')"
        :subheading="__('Every ownership transfer requested for this organization')"
        width="max-w-none"
    >
        @if ($this->transfers->isEmpty())
            <flux:text>{{ __('No ownership transfers yet.') }}</flux:text>
        @else
            <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($this->transfers as $transfer)
                    @php
                        $statusColor = match ($transfer->status) {
                            \App\Enums\OwnershipTransferStatus::Pending => 'amber',
                            \App\Enums\OwnershipTransferStatus::Accepted => 'green',
                            \App\Enums\OwnershipTransferStatus::Declined => 'red',
                            \App\Enums\OwnershipTransferStatus::Cancelled => 'zinc',
                        };

                        $changedAt = match ($transfer->status) {
                            \App\Enums\OwnershipTransferStatus::Pending => [__('Expires at'), $transfer->expires_at],
                            \App\Enums\OwnershipTransferStatus::Accepted => [__('Accepted at'), $transfer->accepted_at],
                            \App\Enums\OwnershipTransferStatus::Declined => [__('Declined at'), $transfer->declined_at],
                            \App\Enums\OwnershipTransferStatus::Cancelled => null,
                        };
                    @endphp

                    <div
                        wire:key="transfer-{{ $transfer->id }}"
                        class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <flux:heading class="truncate">
                                {{ __('Transfer ownership from :from to :to', ['from' => $transfer->fromPerson->name, 'to' => $transfer->toPerson->name]) }}
                            </flux:heading>

                            <flux:text class="text-xs" variant="subtle">
                                {{ __('Created at') }}:
                                <x-local-time :date="$transfer->created_at" format="d M Y H:i" />

                                @if ($changedAt !== null)
                                    - {{ $changedAt[0] }}:
                                    <x-local-time :date="$changedAt[1]" format="d M Y H:i" />
                                @endif
                            </flux:text>
                        </div>

                        <flux:badge size="sm" :color="$statusColor">
                            {{ $transfer->status->label() }}
                        </flux:badge>
                    </div>
                @endforeach
            </div>
        @endif
    </x-org-settings.layout>
</section>
