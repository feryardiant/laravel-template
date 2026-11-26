<x-modal
    flyout
    position="right"
    variant="floating"
    wire:model.self="showModal"
    :title="$this->selectedNotification?->data['title'] ?? null"
>
    @if ($this->selectedNotification)
        @php($data = $this->selectedNotification->data)

        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-3 text-sm">
                @if (! empty($data['greeting']))
                    <p class="font-medium">{{ $data['greeting'] }}</p>
                @endif

                @if (! empty($data['description']))
                    <p>{{ $data['description'] }}</p>
                @endif

                @foreach ($data['lines'] ?? [] as $line)
                    <p>{{ $line }}</p>
                @endforeach

                @if (! empty($data['action']['url']))
                    <flux:button :href="$data['action']['url']" variant="primary" size="sm">
                        {{ $data['action']['label'] ?? '' }}
                    </flux:button>
                @endif

                @if (! empty($data['salutation']))
                    <p>{{ $data['salutation'] }}</p>
                @endif
            </div>
        </div>
    @endif
</x-modal>
