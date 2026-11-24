<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ffmix · Stream Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-neutral-50 dark:bg-neutral-950 text-neutral-800 dark:text-neutral-100 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-10 pb-20">
        <header class="mb-8">
            <h1 class="text-3xl font-light tracking-tight text-neutral-900 dark:text-white">
                ffMix <span class="font-semibold text-primary">Stream</span>
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                HLS capture · DSK overlay · live output
            </p>
        </header>

        @livewire('stream-dashboard')
    </main>

        @include('components.system-footer')
    @livewireScripts
</body>
</html>