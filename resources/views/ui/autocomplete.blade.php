<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Autocomplete</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Text suggestions from local items or a remote endpoint.
        </p>
    </div>

    <section data-ui-demo="autocomplete-local" class="flex flex-col gap-2">
        <x-ui.autocomplete
            wire:model.live="state"
            value-model="stateValue"
            label="State of residence"
            placeholder="Start typing..."
            icon="magnifying-glass"
            clearable
        >
            <x-ui.autocomplete.item disabled>Alabama</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>Alaska</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>Arkansas</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>California</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>Colorado</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>Connecticut</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>New Hampshire</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>New Jersey</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>New Mexico</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>New York</x-ui.autocomplete.item>
        </x-ui.autocomplete>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="state">{{ $state }}</span>
            · Selected value: <span data-ui-bound="state-value">{{ $stateValue }}</span>
        </p>

        <flux:button size="sm" wire:click="setState">Set New York</flux:button>
    </section>

    <section data-ui-demo="autocomplete-remote" class="flex flex-col gap-2">
        <x-ui.autocomplete
            wire:model.live="remoteState"
            value-model="remoteStateValue"
            label="Remote state search"
            placeholder="Type a state..."
            icon="magnifying-glass"
            clearable
            :search-url="route('ui.autocomplete.search')"
            :min-length="1"
            :debounce="150"
        />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="remote-state">{{ $remoteState }}</span>
            · Selected value: <span data-ui-bound="remote-state-value">{{ $remoteStateValue }}</span>
        </p>
    </section>

    <section data-ui-demo="autocomplete-disabled" class="flex flex-col gap-2">
        <x-ui.autocomplete label="Disabled" label:style="opacity: 1" placeholder="Unavailable" value="California" clearable disabled>
            <x-ui.autocomplete.item>California</x-ui.autocomplete.item>
        </x-ui.autocomplete>
    </section>

    <section data-ui-demo="autocomplete-invalid" class="flex flex-col gap-2">
        <x-ui.autocomplete label="Invalid" placeholder="Start typing..." value="California" invalid>
            <x-ui.autocomplete.item>California</x-ui.autocomplete.item>
        </x-ui.autocomplete>
    </section>

    <section data-ui-demo="autocomplete-kbd" class="flex flex-col gap-2">
        <x-ui.autocomplete label="Search orders" placeholder="Search orders..." icon="magnifying-glass" kbd="⌘K" clearable>
            <x-ui.autocomplete.item>Order #1001</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>Order #1002</x-ui.autocomplete.item>
        </x-ui.autocomplete>
    </section>

    <section data-ui-demo="autocomplete-copyable" class="flex flex-col gap-2">
        <x-ui.autocomplete label="API key" value="FLUX-1234-5678-ABCD-EFGH" readonly copyable />
    </section>

    <section data-ui-demo="autocomplete-viewable" class="flex flex-col gap-2">
        <x-ui.autocomplete label="Password" type="password" value="secret-password" viewable />
    </section>

    <section data-ui-demo="autocomplete-mask" class="flex flex-col gap-2">
        <x-ui.autocomplete wire:model.live="masked" label="Expiry date" placeholder="MM/DD/YYYY" mask="99/99/9999" />

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="masked">{{ $masked }}</span>
        </p>
    </section>

    <section data-ui-demo="autocomplete-unlabeled" class="flex flex-col gap-2">
        <x-ui.autocomplete placeholder="Choose a state" clearable>
            <x-ui.autocomplete.item>California</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>New York</x-ui.autocomplete.item>
        </x-ui.autocomplete>
    </section>

    <section data-ui-demo="autocomplete-no-name" class="flex flex-col gap-2">
        <x-ui.autocomplete>
            <x-ui.autocomplete.item>California</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>New York</x-ui.autocomplete.item>
        </x-ui.autocomplete>
    </section>
</div>
