<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        {{-- Favicon dérivé du symbole de la marque (cf. public/brand/). --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" href="/brand/favicon-32.png" sizes="32x32">
        <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=fraunces:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @php
            $authSettings = \App\Models\Content\SiteSetting::current();
            $authImage = $authSettings->auth_image_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($authSettings->auth_image_path)
                : null;
        @endphp

        <div class="min-h-screen flex">
            {{-- Panneau de marque (login/register), éditable depuis l'admin
            (Filament > Réglages du site > Pages de connexion & inscription) :
            image de fond si réglée, sinon le "bandeau signature" ébène + or de
            la charte, comme le hero. Titre et sous-texte éditables aussi. --}}
            <div
                class="hidden lg:flex lg:w-1/2 relative flex-col justify-between p-12 text-brand-surface overflow-hidden bg-brand-ink bg-cover bg-center"
                @if ($authImage) style="background-image: url('{{ $authImage }}');" @endif
            >
                @if ($authImage)
                    {{-- Voile sombre pour garder le texte crème lisible sur la photo. --}}
                    <div class="absolute inset-0 bg-brand-ink/55"></div>
                @else
                    <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_20%_20%,rgba(74,24,51,0.6),transparent_60%)]"></div>
                    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_85%_85%,rgba(74,24,51,0.5),transparent_55%)]"></div>
                @endif

                <x-shop.signature variant="horizontal" class="-ml-3 text-brand-surface" />

                <div class="relative">
                    <p class="font-display text-3xl xl:text-4xl leading-snug max-w-md">
                        {{ $authSettings->auth_title }}
                    </p>
                    <p class="mt-4 text-brand-surface/70 max-w-sm">
                        {{ $authSettings->auth_subtitle }}
                    </p>
                </div>

                <p class="relative text-xs text-brand-surface/50">&copy; {{ now()->year }} Aissatou Store</p>
            </div>

            <div class="flex-1 flex flex-col justify-center items-center bg-brand-surface px-6 py-16">
                <div class="w-full max-w-sm">
                    <x-shop.signature variant="horizontal" :with-slogan="false" class="-ml-3 mb-10 lg:hidden" />

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
