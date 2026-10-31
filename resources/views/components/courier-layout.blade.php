{{-- Pages du livreur : écran de téléphone, sans menu ni panier, jamais indexées. --}}
@props(['title' => 'Livraison'])

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
        <title>{{ $title }} — {{ config('app.name') }}</title>
        <link rel="icon" type="image/png" href="/brand/favicon-32.png" sizes="32x32">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=jost:300,400,500,600|bodoni-moda:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-brand-surface font-sans text-brand-ink antialiased">
        <header class="bg-brand-ink px-4 py-3 text-center">
            <p class="font-display text-lg tracking-[0.2em] text-white">AISSATOU</p>
            <p class="text-[11px] uppercase tracking-[0.25em] text-white/70">Livraison</p>
        </header>

        <main class="mx-auto max-w-md space-y-4 px-4 py-5">
            @if (session('courier_status'))
                <p class="rounded-sm bg-green-50 p-3 text-sm text-green-800">{{ session('courier_status') }}</p>
            @endif
            @if (session('courier_error'))
                <p class="rounded-sm bg-amber-50 p-3 text-sm text-amber-900">{{ session('courier_error') }}</p>
            @endif

            {{ $slot }}
        </main>
    </body>
</html>
