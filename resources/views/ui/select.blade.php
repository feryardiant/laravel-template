<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 data-ui-outside class="text-2xl font-semibold">Select</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Custom listbox and pillbox, bound to Livewire.
        </p>
    </div>

    <section data-ui-demo="select-single" class="flex flex-col gap-2">
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

    <section data-ui-demo="select-multiple" class="flex flex-col gap-2">
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

    <section data-ui-demo="select-pillbox" class="flex flex-col gap-2">
        <x-ui.select wire:model.live="tags" variant="pillbox" label="Tags" placeholder="Choose tags..." clearable>
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

    <section data-ui-demo="select-create" class="flex flex-col gap-2">
        <div wire:key="select-create-{{ count($projects) }}">
            <x-ui.select
                wire:model.live="projectId"
                search-method="searchProjects"
                label="Project"
                placeholder="Choose project..."
            >
                @include('ui.partials.select-projects', ['projects' => $projects])

                <x-ui.select.option.create min-length="2" wire:click="createProject(query)">
                    Create "<span x-text="query.trim()"></span>"
                </x-ui.select.option.create>
            </x-ui.select>
        </div>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Selected: <span data-ui-bound="project">{{ $projectId }}</span>
            · Search: <span data-ui-bound="project-search">{{ $projectSearch }}</span>
        </p>
    </section>

    <section data-ui-demo="select-create-pillbox" class="flex flex-col gap-2">
        <div wire:key="select-create-pillbox-{{ count($tagOptions) }}">
            <x-ui.select
                wire:model.live="tags"
                search-method="searchTags"
                variant="pillbox"
                label="Tags"
                placeholder="Choose or create tags..."
            >
                @include('ui.partials.select-tags', ['tags' => $tagOptions])

                <x-ui.select.option.create min-length="2" wire:click="createTag(query)">
                    Create "<span x-text="query.trim()"></span>"
                </x-ui.select.option.create>
            </x-ui.select>
        </div>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Bound value: <span data-ui-bound="tags">{{ implode(', ', $tags) }}</span>
        </p>
    </section>

    <section data-ui-demo="select-create-modal" class="flex flex-col gap-2">
        <div wire:key="select-create-modal-{{ count($projects) }}">
            <x-ui.select
                wire:model.live="modalProjectId"
                search-method="searchProjects"
                label="Project (modal create)"
                placeholder="Choose project..."
            >
                @include('ui.partials.select-projects', ['projects' => $projects])

                <x-ui.select.option.create min-length="2" wire:click="openCreateProject(query)">Create new</x-ui.select.option.create>
            </x-ui.select>
        </div>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Selected: <span data-ui-bound="modal-project">{{ $modalProjectId }}</span>
        </p>

        <x-modal wire:model.self="showCreateModal" title="Create new project">
            <form wire:submit="createProjectFromModal" class="flex flex-col gap-6">
                <flux:input wire:model="newProjectName" label="Name" placeholder="e.g. 'UX Research'" autofocus />

                <div class="flex items-center justify-between">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cancel</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary">Create</flux:button>
                </div>
            </form>
        </x-modal>
    </section>

    <section data-ui-demo="select-disabled" class="flex flex-col gap-2">
        <x-ui.select label="Disabled" label:style="opacity: 1" placeholder="Unavailable" value="web" clearable disabled>
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-invalid" class="flex flex-col gap-2">
        <x-ui.select label="Invalid" placeholder="Choose industry..." value="web" invalid>
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-unlabeled" class="flex flex-col gap-2">
        <x-ui.select placeholder="Choose something" clearable>
            <x-ui.select.option value="one">First option</x-ui.select.option>
            <x-ui.select.option value="two">Second option</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-disabled-options" class="flex flex-col gap-2">
        <x-ui.select label="Disabled options" placeholder="Choose an option...">
            <x-ui.select.option value="blocked" disabled>Blocked option</x-ui.select.option>
            <x-ui.select.option value="available">Available option</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-plain" class="flex flex-col gap-3">
        <form method="get" action="{{ route('ui.select') }}" class="flex flex-col gap-3">
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

    <section data-ui-demo="select-duplicate-model" class="flex flex-col gap-2">
        <x-ui.select wire:model="industry" label="Duplicate A" placeholder="Choose industry...">
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>

        <x-ui.select wire:model="industry" label="Duplicate B" placeholder="Choose industry...">
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-no-name" class="flex flex-col gap-2">
        <x-ui.select>
            <x-ui.select.option value="one">First option</x-ui.select.option>
            <x-ui.select.option value="two">Second option</x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-wide" class="flex flex-col gap-2">
        <x-ui.select label="Wide options" placeholder="Choose..." options:class="w-[40rem]">
            <x-ui.select.option value="long">
                VeryLongOptionLabelWithoutAnySpacesThatWouldOverflowTheViewportOnMobile
            </x-ui.select.option>
        </x-ui.select>
    </section>

    <section data-ui-demo="select-field-nested" class="flex flex-col gap-2">
        <flux:field>
            <flux:label>Nested field</flux:label>

            <x-ui.select name="nested_industry" placeholder="Pick one">
                <x-ui.select.option value="design">Design services</x-ui.select.option>
                <x-ui.select.option value="web">Web development</x-ui.select.option>
            </x-ui.select>

            <flux:description>Outer field description</flux:description>
        </flux:field>
    </section>

    <section data-ui-demo="select-form-reset" class="flex flex-col gap-2">
        <form class="flex flex-col gap-2">
            <x-ui.select name="reset_industry" value="web" label="Reset industry">
                <x-ui.select.option value="web">Web development</x-ui.select.option>
                <x-ui.select.option value="design">Design services</x-ui.select.option>
            </x-ui.select>

            <flux:button type="reset" size="sm" data-ui-test="reset-select">Reset</flux:button>
        </form>
    </section>
</div>
