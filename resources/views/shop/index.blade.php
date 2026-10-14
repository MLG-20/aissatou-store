@php
    // Le catalogue sert deux entrées de la maquette : « Nouveautés » et le
    // catalogue complet. Même grille, mêmes filtres — seul le bandeau de tête
    // change, pour que la page dise toujours ce qu'elle montre.
    $heading = $showingNewArrivals
        ? ['eyebrow' => 'À découvrir', 'title' => 'Nouveautés', 'intro' => 'Les pièces que nous venons de faire entrer dans la sélection.']
        : ['eyebrow' => 'La sélection', 'title' => 'Tout le catalogue', 'intro' => "Hijabs, foulards, cols, parfums et objets à offrir — moins d'objets, mieux choisis."];
@endphp

<x-shop-layout :title="$heading['title'].' — '.config('app.name')">
    {{-- Bandeau de tête en Parchemin plein, sans dégradé : le rapport réserve la
         respiration au fond et proscrit les ornements décoratifs (p.9). --}}
    <section class="w-full bg-brand-parchment border-b border-brand-ink/10 py-14 sm:py-20">
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
            <span class="text-[0.65rem] uppercase tracking-[0.25em] text-brand-signature">{{ $heading['eyebrow'] }}</span>
            <h1 class="font-display text-4xl sm:text-5xl text-brand-ink mt-2">{{ $heading['title'] }}</h1>
            <p class="mt-3 text-brand-muted max-w-xl">{{ $heading['intro'] }}</p>
        </div>
    </section>

    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8">
        <x-shop.filter-bar :action="route('shop.index')" :categories="$categories" :materials="$materials">
            @include('shop.partials.product-grid', ['products' => $products])
        </x-shop.filter-bar>
    </div>
</x-shop-layout>
