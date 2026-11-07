@extends('ui.layout')

@section('content')
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
    </div>
@endsection
