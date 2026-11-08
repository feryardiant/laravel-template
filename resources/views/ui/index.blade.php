<div class="space-y-8">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold">UI components</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Development gallery for the first-party component library.
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <a
            href="{{ route('ui.select') }}"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20"
        >
            <div class="font-medium">Select</div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Listbox, multiple, groups, icons, and pillbox.</div>
        </a>

        <a
            href="{{ route('ui.autocomplete') }}"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20"
        >
            <div class="font-medium">Autocomplete</div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Local suggestions and remote search.</div>
        </a>

        <a
            href="{{ route('ui.calendar') }}"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20"
        >
            <div class="font-medium">Calendar</div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Single, multiple and range dates.</div>
        </a>

        <a
            href="{{ route('ui.date-picker') }}"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20"
        >
            <div class="font-medium">Date picker</div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Popover calendar with ranges and presets.</div>
        </a>

        <a
            href="{{ route('ui.time-picker') }}"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20"
        >
            <div class="font-medium">Time picker</div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Hour and minute columns with multiple selection.</div>
        </a>

        <a
            href="{{ route('ui.datetime-picker') }}"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20"
        >
            <div class="font-medium">Date and time picker</div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Calendar and time columns for single dates and ranges.</div>
        </a>

        <a
            href="{{ route('ui.file-upload') }}"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20"
        >
            <div class="font-medium">File upload</div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Drag and drop, upload progress and file items.</div>
        </a>
    </div>

    <section data-ui-demo="popover-scroll" class="space-y-4">
        <div class="max-w-sm">
            <x-ui.date-picker
                name="scroll_date"
                label="Scroll target"
                value="2026-11-08"
                open-to="2026-11-01"
                clearable
            />
        </div>

        <div class="flex h-[150vh] items-start justify-center rounded-xl border-2 border-dashed border-zinc-200 p-6 text-sm text-zinc-500 dark:border-white/10 dark:text-zinc-400">
            Scroll down to exercise popover repositioning. The picker above should stay inside the viewport.
        </div>
    </section>
</div>
