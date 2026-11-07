<div class="space-y-8">
    <div class="space-y-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Select</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Custom listbox and pillbox, bound to Livewire.
        </p>
    </div>

    <section data-ui-demo="select-single" class="space-y-2">
        <x-ui.select wire:model.live="industry" label="Industry" placeholder="Choose industry..." empty="No industries found" clearable>
            <x-ui.select.option value="photography" icon="camera">Photography</x-ui.select.option>
            <x-ui.select.option value="design" icon="paint-brush">Design services</x-ui.select.option>

            <x-ui.select.group label="Technology">
                <x-ui.select.option value="web" icon="code-bracket" description="Websites and web applications">
                    Web development
                </x-ui.select.option>
                <x-ui.select.option value="it" icon="server" description="Infrastructure and support">
                    IT consulting
                </x-ui.select.option>
            </x-ui.select.group>

            <x-ui.select.option value="accounting" icon="calculator">Accounting</x-ui.select.option>
            <x-ui.select.option value="legal" icon="scale">Legal services</x-ui.select.option>
            <x-ui.select.option value="consulting" icon="briefcase">Consulting</x-ui.select.option>
            <x-ui.select.option value="other" icon="ellipsis-horizontal">Other</x-ui.select.option>
        </x-ui.select>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="industry">{{ $industry }}</span>
        </p>

        <flux:button size="sm" wire:click="resetIndustry">Set legal</flux:button>
    </section>

    <section data-ui-demo="select-multiple" class="space-y-2">
        <x-ui.select wire:model.live="industries" label="Industries" placeholder="Choose industries..." multiple clearable>
            <x-ui.select.option value="photography" icon="camera">Photography</x-ui.select.option>
            <x-ui.select.option value="design" icon="paint-brush">Design services</x-ui.select.option>
            <x-ui.select.option value="web" icon="code-bracket">Web development</x-ui.select.option>
            <x-ui.select.option value="it" icon="server">IT consulting</x-ui.select.option>
            <x-ui.select.option value="accounting" icon="calculator">Accounting</x-ui.select.option>
            <x-ui.select.option value="legal" icon="scale">Legal services</x-ui.select.option>
        </x-ui.select>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="industries">{{ implode(', ', $industries) }}</span>
        </p>
    </section>

    <section data-ui-demo="select-pillbox" class="space-y-2">
        <x-ui.select wire:model.live="tags" variant="pillbox" label="Tags" placeholder="Choose tags...">
            <x-ui.select.option value="design">Design</x-ui.select.option>
            <x-ui.select.option value="development">Development</x-ui.select.option>
            <x-ui.select.option value="marketing">Marketing</x-ui.select.option>
            <x-ui.select.option value="sales">Sales</x-ui.select.option>
            <x-ui.select.option value="support">Support</x-ui.select.option>
            <x-ui.select.option value="engineering">Engineering</x-ui.select.option>
            <x-ui.select.option value="product">Product</x-ui.select.option>
            <x-ui.select.option value="operations">Operations</x-ui.select.option>
        </x-ui.select>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="tags">{{ implode(', ', $tags) }}</span>
        </p>
    </section>

    <section data-ui-demo="select-disabled" class="space-y-2">
        <x-ui.select label="Disabled" label:style="opacity: 1" placeholder="Unavailable" value="web" clearable disabled>
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-invalid" class="space-y-2">
        <x-ui.select label="Invalid" placeholder="Choose industry..." value="web" invalid>
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-unlabeled" class="space-y-2">
        <x-ui.select placeholder="Choose something" clearable>
            <x-ui.select.option value="one">First option</x-ui.select.option>
            <x-ui.select.option value="two">Second option</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-disabled-options" class="space-y-2">
        <x-ui.select label="Disabled options" placeholder="Choose an option...">
            <x-ui.select.option value="blocked" disabled>Blocked option</x-ui.select.option>
            <x-ui.select.option value="available">Available option</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-plain" class="space-y-3">
        <form method="get" action="{{ route('ui.select') }}" class="space-y-3">
            <x-ui.select name="plain_industry" value="design" label="Plain single" placeholder="Choose industry...">
                <x-ui.select.option value="web">Web development</x-ui.select.option>
                <x-ui.select.option value="design">Design services</x-ui.select.option>
            </x-ui.select>

            <x-ui.select name="plain_tags" :value="['design']" multiple label="Plain multiple" placeholder="Choose tags...">
                <x-ui.select.option value="design">Design</x-ui.select.option>
                <x-ui.select.option value="marketing">Marketing</x-ui.select.option>
            </x-ui.select>

            <flux:button type="submit" size="sm">Submit plain form</flux:button>
        </form>
    </section>

    <section data-ui-demo="select-duplicate-model" class="space-y-2">
        <x-ui.select wire:model="industry" label="Duplicate A" placeholder="Choose industry...">
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>

        <x-ui.select wire:model="industry" label="Duplicate B" placeholder="Choose industry...">
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-no-name" class="space-y-2">
        <x-ui.select>
            <x-ui.select.option value="one">First option</x-ui.select.option>
            <x-ui.select.option value="two">Second option</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-wide" class="space-y-2">
        <x-ui.select label="Wide options" placeholder="Choose..." options:class="w-[40rem]">
            <x-ui.select.option value="long">
                VeryLongOptionLabelWithoutAnySpacesThatWouldOverflowTheViewportOnMobile
            </x-ui.select.option>
        </x-ui.select>
    </section>
</div>
