<div class="space-y-8">
    <div class="space-y-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Timeline</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Ordered events with connector lines, statuses and a horizontal layout.
        </p>
    </div>

    <section data-ui-demo="timeline-vertical" class="space-y-3">
        <x-ui.timeline>
            <x-ui.timeline.item status="complete" indicator:icon="check">
                <flux:heading>Order confirmed</flux:heading>
                <flux:text>Payment received · 4 days ago</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="current" indicator:icon="truck">
                <flux:heading>On its way</flux:heading>
                <flux:text>Left the warehouse · 2 days ago</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="incomplete" indicator:icon="home">
                <flux:heading>Delivered</flux:heading>
                <flux:text>Estimated in 2 days</flux:text>
            </x-ui.timeline.item>
        </x-ui.timeline>
    </section>

    <section data-ui-demo="timeline-vertical-aligned" class="space-y-3">
        <x-ui.timeline>
            <x-ui.timeline.item align="start" indicator:icon="arrow-up">
                <flux:heading>Start align</flux:heading>
                <flux:text>The indicator sits on the first line.</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item align="center" indicator:icon="arrows-up-down">
                <flux:heading>Center align</flux:heading>
                <flux:text>The indicator is centered against the content.</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item align="end" indicator:icon="arrow-down">
                <flux:heading>End align</flux:heading>
                <flux:text>The indicator sits on the last line.</flux:text>
            </x-ui.timeline.item>
        </x-ui.timeline>
    </section>

    <section data-ui-demo="timeline-horizontal" class="space-y-3">
        <x-ui.timeline mode="horizontal">
            <x-ui.timeline.item status="complete" indicator:icon="credit-card">
                <flux:heading>Order confirmed</flux:heading>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="complete" indicator:icon="banknotes">
                <flux:heading>Payment received</flux:heading>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="complete" indicator:icon="cube">
                <flux:heading>Packed in warehouse</flux:heading>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="current" indicator:icon="truck">
                <flux:heading>On its way</flux:heading>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="incomplete" indicator:icon="map-pin">
                <flux:heading>Out for delivery</flux:heading>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="incomplete" indicator:icon="home">
                <flux:heading>Delivered</flux:heading>
            </x-ui.timeline.item>
        </x-ui.timeline>
    </section>

    <section data-ui-demo="timeline-horizontal-aligned" class="space-y-3">
        <x-ui.timeline mode="horizontal">
            <x-ui.timeline.item align="start" indicator:icon="arrow-left">
                <flux:heading>Start align</flux:heading>
                <flux:text>The indicator sits on the first line.</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item align="center" indicator:icon="arrows-right-left">
                <flux:heading>Center align</flux:heading>
                <flux:text>The indicator is centered against the content.</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item align="end" indicator:icon="arrow-right">
                <flux:heading>End align</flux:heading>
                <flux:text>The indicator sits on the last line.</flux:text>
            </x-ui.timeline.item>
        </x-ui.timeline>
    </section>

    <section data-ui-demo="timeline-dots" class="space-y-3">
        <x-ui.timeline align="start">
            <x-ui.timeline.item>
                <flux:text>Draft created · 2 days ago</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item>
                <flux:text>Edits made · 1 day ago</flux:text>
            </x-ui.timeline.item>

            <x-ui.timeline.item>
                <flux:text>Published · just now</flux:text>
            </x-ui.timeline.item>
        </x-ui.timeline>
    </section>

    <section data-ui-demo="timeline-bare" class="space-y-3">
        <x-ui.timeline>
            <x-ui.timeline.item status="complete" indicator:icon="document-text" indicator:variant="bare">
                <flux:heading>Draft created</flux:heading>
            </x-ui.timeline.item>

            <x-ui.timeline.item status="current" indicator:icon="pencil-square" indicator:variant="bare">
                <flux:heading>Edits made</flux:heading>
            </x-ui.timeline.item>

            <x-ui.timeline.item
                status="incomplete"
                indicator:icon="rocket-launch"
                indicator:variant="bare"
                class:indicator="size-8 text-amber-500 dark:text-amber-400"
            >
                <flux:heading>Published</flux:heading>
            </x-ui.timeline.item>
        </x-ui.timeline>
    </section>
</div>
