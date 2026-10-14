@props(['action', 'categories' => null, 'materials' => null])

{{-- Panneau de filtres latéral de la maquette p.12 : « Filtres structurés :
     prix, matière, collection et disponibilité » à gauche, grille à droite.

     Sur mobile, le rapport demande « filtres dans un panneau » : le bloc devient
     un <details> replié, pour que la grille reste immédiatement visible.

     Les filtres Matière (actif depuis la Phase 2.2A) et Collection (Phase 2.2B, à venir) de la maquette — cf. docs/files/02.2-modules-maquette.md. --}}
@php
    // Tranches de prix en FCFA, proposées en raccourci. Elles pilotent les mêmes
    // champs min_price/max_price que la saisie libre : aucun paramètre nouveau
    // côté serveur, donc aucune validation à modifier.
    $priceRanges = [
        ['label' => 'Moins de 25 000 FCFA', 'min' => '', 'max' => '25000'],
        ['label' => '25 000 à 50 000 FCFA', 'min' => '25000', 'max' => '50000'],
        ['label' => '50 000 à 100 000 FCFA', 'min' => '50000', 'max' => '100000'],
        ['label' => 'Plus de 100 000 FCFA', 'min' => '100000', 'max' => ''],
    ];
@endphp

<div
    x-data="productFilters({
        action: @js($action),
        initial: {
            q: @js(request('q', '')),
            category_id: @js((string) request('category_id', '')),
            material_id: @js((string) request('material_id', '')),
            min_price: @js((string) request('min_price', '')),
            max_price: @js((string) request('max_price', '')),
            in_stock: @js(request()->boolean('in_stock')),
            nouveautes: @js(request()->boolean('nouveautes')),
        },
    })"
    class="grid gap-10 lg:grid-cols-[16rem,1fr]"
>
    {{-- Le panneau est toujours déplié sur grand écran, et replié par défaut sur
         mobile pour que la grille reste immédiatement visible. `desktop` suit la
         media query en direct : replier la fenêtre ne laisse pas le panneau
         coincé dans un état incohérent. --}}
    <form
        @submit.prevent="apply()"
        class="lg:sticky lg:top-24 lg:self-start"
        x-data="{ open: false, desktop: window.matchMedia('(min-width: 1024px)').matches }"
        x-init="window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => desktop = e.matches)"
    >
        <button
            type="button"
            class="lg:hidden w-full border border-brand-ink/15 px-4 py-3 text-xs uppercase tracking-[0.15em]"
            @click="open = !open"
            :aria-expanded="open"
        >
            <span x-text="open ? 'Masquer les filtres' : 'Filtrer'">Filtrer</span>
        </button>

        <div x-show="open || desktop" x-cloak class="flex flex-col gap-8 pt-6 lg:pt-0">
                <div class="flex flex-col gap-2" id="recherche">
                    <label class="text-[0.65rem] uppercase tracking-[0.18em] text-brand-muted" for="filter-q">Rechercher</label>
                    <input
                        id="filter-q"
                        type="search"
                        x-model="filters.q"
                        @input.debounce.400ms="apply()"
                        placeholder="Nom du produit…"
                        class="w-full rounded-sm border-brand-ink/20 bg-transparent text-sm focus:border-brand-signature focus:ring-brand-accent"
                    >
                </div>

                @if ($categories)
                    <div class="flex flex-col gap-3 border-t border-brand-ink/10 pt-6">
                        <span class="text-[0.65rem] uppercase tracking-[0.18em] text-brand-muted">Catégorie</span>

                        <label class="flex items-center gap-2.5 text-sm">
                            <input type="radio" value="" x-model="filters.category_id" @change="apply()" class="border-brand-ink/30 text-brand-accent focus:ring-brand-accent">
                            Toutes
                        </label>

                        @foreach ($categories as $category)
                            <label class="flex items-center gap-2.5 text-sm">
                                <input type="radio" value="{{ $category->id }}" x-model="filters.category_id" @change="apply()" class="border-brand-ink/30 text-brand-accent focus:ring-brand-accent">
                                {{ $category->name }}
                            </label>
                        @endforeach
                    </div>
                @endif

                @if ($materials)
                    <div class="flex flex-col gap-3 border-t border-brand-ink/10 pt-6">
                        <span class="text-[0.65rem] uppercase tracking-[0.18em] text-brand-muted">Matière</span>

                        <label class="flex items-center gap-2.5 text-sm">
                            <input type="radio" value="" x-model="filters.material_id" @change="apply()" class="border-brand-ink/30 text-brand-accent focus:ring-brand-accent">
                            Toutes
                        </label>

                        @foreach ($materials as $material)
                            <label class="flex items-center gap-2.5 text-sm">
                                <input type="radio" value="{{ $material->id }}" x-model="filters.material_id" @change="apply()" class="border-brand-ink/30 text-brand-accent focus:ring-brand-accent">
                                {{ $material->name }}
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-col gap-3 border-t border-brand-ink/10 pt-6">
                    <span class="text-[0.65rem] uppercase tracking-[0.18em] text-brand-muted">Prix</span>

                    @foreach ($priceRanges as $range)
                        <label class="flex items-center gap-2.5 text-sm">
                            <input
                                type="radio"
                                name="price-range"
                                @change="filters.min_price = @js($range['min']); filters.max_price = @js($range['max']); apply()"
                                :checked="filters.min_price === @js($range['min']) && filters.max_price === @js($range['max'])"
                                class="border-brand-ink/30 text-brand-accent focus:ring-brand-accent"
                            >
                            {{ $range['label'] }}
                        </label>
                    @endforeach

                    {{-- Saisie libre conservée : les tranches ne sont qu'un raccourci. --}}
                    <div class="mt-2 flex items-center gap-2">
                        <input
                            type="number"
                            min="0"
                            x-model="filters.min_price"
                            @input.debounce.400ms="apply()"
                            aria-label="Prix minimum"
                            placeholder="Min"
                            class="w-full rounded-sm border-brand-ink/20 bg-transparent text-xs focus:border-brand-signature focus:ring-brand-accent"
                        >
                        <span class="text-brand-muted">–</span>
                        <input
                            type="number"
                            min="0"
                            x-model="filters.max_price"
                            @input.debounce.400ms="apply()"
                            aria-label="Prix maximum"
                            placeholder="Max"
                            class="w-full rounded-sm border-brand-ink/20 bg-transparent text-xs focus:border-brand-signature focus:ring-brand-accent"
                        >
                    </div>
                </div>

                {{-- « Sélection » : les signaux commerciaux du rapport (p.13)
                     deviennent des critères de tri. Pour l'instant seul le
                     signal Nouveauté est filtrable — c'est l'onglet de la
                     maquette ; Sélectionné et Édition limitée suivront avec les
                     autres filtres de la p.12 en Phase 2.2. --}}
                <div class="flex flex-col gap-3 border-t border-brand-ink/10 pt-6">
                    <span class="text-[0.65rem] uppercase tracking-[0.18em] text-brand-muted">Sélection</span>

                    {{-- Navigation complète, et non un rafraîchissement de la
                         grille : ce filtre change le bandeau de tête de la page
                         (« Nouveautés » ou « Tout le catalogue »), or `apply()`
                         ne remplace que la grille. En AJAX, le titre resterait
                         donc en contradiction avec ce qui est affiché dessous. --}}
                    <label class="flex items-center gap-2.5 text-sm">
                        <input
                            type="checkbox"
                            x-model="filters.nouveautes"
                            @change="filters.nouveautes = $event.target.checked; window.location = buildUrl()"
                            class="rounded-sm border-brand-ink/30 text-brand-accent focus:ring-brand-accent"
                        >
                        Nouveautés uniquement
                    </label>
                </div>

                <div class="flex flex-col gap-3 border-t border-brand-ink/10 pt-6">
                    <span class="text-[0.65rem] uppercase tracking-[0.18em] text-brand-muted">Disponibilité</span>

                    <label class="flex items-center gap-2.5 text-sm">
                        <input type="checkbox" x-model="filters.in_stock" @change="apply()" class="rounded-sm border-brand-ink/30 text-brand-accent focus:ring-brand-accent">
                        En stock uniquement
                    </label>
                </div>
        </div>
    </form>

    <div x-ref="grid">
        {{ $slot }}
    </div>
</div>
