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
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Event') }}</flux:table.column>
                <flux:table.column>{{ __('Actor') }}</flux:table.column>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Details') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->logs as $log)
                    @php
                        $changes = $log->changes ?? [];
                        $details = [];

                        if (isset($changes['from'], $changes['to'])) {
                            $details[] = \App\Enums\Role::from($changes['from'])->label()
                                .' → '
                                .\App\Enums\Role::from($changes['to'])->label();
                        }

                        if (isset($changes['fields'])) {
                            $fields = is_array($changes['fields']) ? $changes['fields'] : [];

                            $details[] = __('Fields').': '.implode(', ', array_map(
                                fn (string $field): string => \App\Models\AuditLog::fieldLabel($field),
                                $fields,
                            ));
                        }

                        if (isset($changes['feature'])) {
                            $details[] = __(':feature: :state', [
                                'feature' => \App\Models\AuditLog::featureLabel((string) $changes['feature']),
                                'state' => $changes['enabled'] ? __('On') : __('Off'),
                            ]);
                        }

                        if (isset($changes['type'])) {
                            $details[] = str_starts_with($log->event->value, 'approval.')
                                ? \App\Enums\ApprovalRequestType::from($changes['type'])->label()
                                : \App\Enums\OrganizationRelationshipType::from($changes['type'])->label();
                        }

                        if (isset($changes['role']) && ! isset($changes['type'])) {
                            $details[] = \App\Enums\Role::from($changes['role'])->label();
                        }

                        if (isset($changes['via'])) {
                            $via = match ($changes['via']) {
                                'manager' => __('Manager'),
                                'self' => __('Self'),
                                'approval' => __('Approval'),
                                default => $changes['via'],
                            };

                            $details[] = __('Via :via', ['via' => $via]);
                        }

                        if (isset($changes['last_membership']) && $changes['last_membership']) {
                            $details[] = __('Last membership');
                        }

                        if (isset($changes['restored']) && $changes['restored']) {
                            $details[] = __('Restored');
                        }

                        if (isset($changes['reason'])) {
                            $reason = match ($changes['reason']) {
                                'role_changed' => __('Role changed'),
                                'member_removed' => __('Member removed'),
                                'member_left' => __('Member left'),
                                'organization_deleted' => __('Organization deleted'),
                                default => $changes['reason'],
                            };

                            $details[] = __('Reason').': '.$reason;
                        }

                        if (isset($changes['detached_only']) && $changes['detached_only']) {
                            $details[] = __('Entity kept');
                        }

                        $color = match (true) {
                            str_starts_with($log->event->value, 'member.') => 'sky',
                            str_starts_with($log->event->value, 'contact.') => 'emerald',
                            str_starts_with($log->event->value, 'organization.') => 'amber',
                            str_starts_with($log->event->value, 'feature.') => 'violet',
                            default => 'indigo',
                        };
                    @endphp

                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="whitespace-nowrap">
                            <x-local-time :date="$log->created_at" format="d M Y H:i" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$color">{{ $log->event->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $log->actor?->name ?? __('System') }}</flux:table.cell>
                        <flux:table.cell>{{ $log->subjectLabel() }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">
                            {{ $details === [] ? '—' : implode(', ', $details) }}
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
