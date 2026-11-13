<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Organization settings') }}</flux:heading>

    <x-settings.layout :heading="__('Organization')" :subheading="__('Manage your membership in the current organization')">
        @if ($organization === null)
            <flux:text>{{ __('You are not a member of any organization.') }}</flux:text>
        @else
            <div class="space-y-2">
                <flux:heading>{{ $organization->name }}</flux:heading>

                @if ($role !== null)
                    <flux:text>{{ __('Your role') }}: {{ $role->label() }}</flux:text>
                @endif
            </div>

            <section class="mt-10 space-y-6">
                <div class="relative mb-5">
                    <flux:heading>{{ __('Leave organization') }}</flux:heading>
                    <flux:subheading>{{ __('Remove your membership from this organization') }}</flux:subheading>
                </div>

                @if ($isLastOwner)
                    <flux:text variant="subtle">{{ __('You cannot leave while you are the last owner.') }}</flux:text>
                @else
                    <flux:modal.trigger name="leave-organization">
                        <flux:button variant="danger">{{ __('Leave organization') }}</flux:button>
                    </flux:modal.trigger>

                    <flux:modal name="leave-organization" class="max-w-lg">
                        <form wire:submit="leave" class="space-y-6">
                            <div>
                                <flux:heading size="lg">
                                    {{ __('Leave :organization?', ['organization' => $organization->name]) }}
                                </flux:heading>

                                <flux:subheading>
                                    {{ __('You will lose access to this organization. An owner or admin can add you back later.') }}
                                </flux:subheading>
                            </div>

                            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                                <flux:modal.close>
                                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>

                                <flux:button variant="danger" type="submit">{{ __('Leave organization') }}</flux:button>
                            </div>
                        </form>
                    </flux:modal>
                @endif
            </section>
        @endif
    </x-settings.layout>
</section>
