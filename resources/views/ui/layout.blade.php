<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-white">
        <main class="mx-auto w-full max-w-3xl space-y-8 p-6">
            <a href="{{ route('ui.index') }}" class="inline-flex text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-white">
                &larr; UI gallery
            </a>

            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </body>
</html>
