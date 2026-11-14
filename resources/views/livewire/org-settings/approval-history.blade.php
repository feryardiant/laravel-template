<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Organization settings') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Manage your organization settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading level="2" class="sr-only">{{ __('Approvals history') }}</flux:heading>

    <x-org-settings.layout
        :heading="__('Approvals history')"
        :subheading="__('Every approval request made in this organization')"
        width="max-w-none"
    >
        @if ($this->approvals->isEmpty())
            <flux:text>{{ __('No approval requests yet.') }}</flux:text>
        @else
            <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($this->approvals as $approval)
                    @php
                        $statusColor = match ($approval->status) {
                            \App\Enums\ApprovalRequestStatus::Pending => 'amber',
                            \App\Enums\ApprovalRequestStatus::Accepted => 'green',
                            \App\Enums\ApprovalRequestStatus::Declined => 'red',
                            \App\Enums\ApprovalRequestStatus::Cancelled => 'zinc',
                        };

                        $changedAt = match ($approval->status) {
                            \App\Enums\ApprovalRequestStatus::Pending => [__('Expires at'), $approval->expires_at],
                            \App\Enums\ApprovalRequestStatus::Accepted => [__('Accepted at'), $approval->accepted_at],
                            \App\Enums\ApprovalRequestStatus::Declined => [__('Declined at'), $approval->declined_at],
                            \App\Enums\ApprovalRequestStatus::Cancelled => null,
                        };
                    @endphp

                    <div
                        wire:key="approval-{{ $approval->id }}"
                        class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <flux:heading class="truncate">
                                @if ($approval->type === \App\Enums\ApprovalRequestType::OwnershipTransfer)
                                    {{ __('Transfer ownership from :from to :to', ['from' => $approval->requester->name, 'to' => $approval->approver->name]) }}
                                @else
                                    {{ __(':name requested to leave', ['name' => $approval->requester->name]) }}
                                @endif
                            </flux:heading>

                            <flux:text class="text-xs" variant="subtle">
                                {{ __('Created at') }}:
                                <x-local-time :date="$approval->created_at" format="d M Y H:i" />

                                @if ($changedAt !== null)
                                    - {{ $changedAt[0] }}:
                                    <x-local-time :date="$changedAt[1]" format="d M Y H:i" />
                                @endif
                            </flux:text>
                        </div>

                        <flux:badge size="sm" :color="$statusColor">
                            {{ $approval->status->label() }}
                        </flux:badge>
                    </div>
                @endforeach
            </div>
        @endif
    </x-org-settings.layout>
</section>
