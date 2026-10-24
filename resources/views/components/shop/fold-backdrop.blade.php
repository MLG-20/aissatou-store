{{-- « Fond abstrait inspiré du pli » — annotation 04 MATIÈRE de la maquette de la
     page d'accueil (rapport d'identité p.10). Le hero de la maquette n'est pas une
     photographie : c'est un fond abstrait dérivé du pli textile, qui est aussi le
     troisième geste du logo (p.4) et le motif « Fold Path » du langage graphique
     (p.9).

     Rendu en SVG inline plutôt qu'en image : aucun fichier à charger, ça reste net
     à toutes les tailles, et les couleurs viennent du système — le rapport
     interdit les couleurs hors palette (p.6).

     Les courbes sont des bandes translucides qui se recouvrent : elles évoquent la
     matière et le tombé (principe non négociable n°3) sans devenir un « motif trop
     dense », que la p.9 proscrit. --}}
@props([
    // L'incision Garance n'a de sens que sur une grande surface (hero) : sur
    // une liste de liens (menu mobile), elle passe pour un trait parasite.
    'cut' => true,
])

<div {{ $attributes->merge(['class' => 'absolute inset-0 overflow-hidden bg-brand-ink']) }} aria-hidden="true">
    <svg class="h-full w-full" viewBox="0 0 1440 720" preserveAspectRatio="xMidYMid slice" fill="none">
        <defs>
            {{-- Profondeur : Cassis en haut à gauche, Encre ailleurs. --}}
            <radialGradient id="fold-depth" cx="18%" cy="12%" r="85%">
                <stop offset="0%" stop-color="#4A1833" stop-opacity="0.75" />
                <stop offset="100%" stop-color="#17151B" stop-opacity="0" />
            </radialGradient>

            {{-- Lumière rasante sur l'arête du pli. --}}
            <linearGradient id="fold-sheen" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#F4E6D5" stop-opacity="0.14" />
                <stop offset="55%" stop-color="#F4E6D5" stop-opacity="0.04" />
                <stop offset="100%" stop-color="#F4E6D5" stop-opacity="0" />
            </linearGradient>

            <linearGradient id="fold-sage" x1="1" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#A7AE91" stop-opacity="0.12" />
                <stop offset="100%" stop-color="#A7AE91" stop-opacity="0" />
            </linearGradient>
        </defs>

        <rect width="1440" height="720" fill="url(#fold-depth)" />

        {{-- Bandes de tombé : mêmes courbes décalées, comme les plis successifs
             d'une étoffe suspendue. --}}
        <path d="M-100 720 C 220 460, 300 300, 260 -40 L 620 -40 C 660 320, 540 520, 300 720 Z" fill="url(#fold-sheen)" />
        <path d="M260 720 C 600 470, 700 300, 660 -40 L 980 -40 C 1020 330, 900 530, 660 720 Z" fill="url(#fold-sage)" />
        <path d="M700 720 C 1040 480, 1140 310, 1100 -40 L 1420 -40 C 1460 340, 1340 540, 1100 720 Z" fill="url(#fold-sheen)" />

        {{-- « Edit Cut » : l'incision Garance, brève, qui signale le choix (p.9).
             Un seul trait, jamais un aplat. --}}
        @if ($cut)
            <path d="M-40 566 C 420 500, 1000 626, 1480 548" stroke="#9F2D40" stroke-width="1.5" stroke-opacity="0.55" />
        @endif
    </svg>
</div>
