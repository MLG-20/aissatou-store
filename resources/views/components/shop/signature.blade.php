@props([
    'variant' => 'vertical',
    'withSlogan' => true,
    // `lg` : panneau de marque des pages de connexion/inscription, où la
    // signature est seule sur une photo et doit se lire de loin.
    'size' => 'md',
])

{{-- SIGNATURE DE LA MARQUE.

     Deux verrouillages officiels (rapport d'identité p.5) :
     · verticale   — symbole au-dessus, texte dessous ;
     · horizontale — symbole à gauche, texte à droite.

     Le rapport écrit p.5 « Horizontale : en-têtes, navigation », mais la maquette
     de la page d'accueil (p.10) utilise la VERTICALE dans son en-tête, et son
     annotation 01 précise « Catégories visibles et logo centré ». Sur cet écran
     la maquette fait foi : elle est plus spécifique qu'un principe d'usage
     général. D'où `variant="vertical"` par défaut, utilisé dans l'en-tête.

     Normes de la page 6, respectées ici :
     · largeur minimale 140 px sans slogan, 220 px avec ;
     · zone de protection = largeur optique du jambage principal (X) : aucun
       texte, bord, photo ni motif ne doit y entrer. Le padding est porté par le
       composant, et non par un `gap` du parent qui ne protégerait qu'un côté ;
     · ne jamais enfermer le signe dans un cadre proche.

     ⚠️ Le symbole affiché vient d'un PNG fourni par la cliente, détouré et
     débarrassé de son filigrane (cf. public/brand/ et la classe `.brand-symbol`).
     Ce n'est PAS le `logo_symbole_seul.svg` officiel listé p.17, qui n'a pas été
     transmis : le rendu est monochrome et perd l'accent Garance du signe.
     À remplacer par le SVG dès réception — seule la classe CSS changera. --}}
@php
    $vertical = $variant === 'vertical';
    $large = $size === 'lg';
@endphp

<a
    href="{{ route('home') }}"
    {{ $attributes->merge([
        'class' => 'flex px-3 py-1 '
            .($vertical ? 'flex-col items-center gap-1 min-w-[140px]' : 'flex-row items-center gap-3 '.($withSlogan ? 'min-w-[220px]' : 'min-w-[140px]')),
    ]) }}
    aria-label="Aissatou Store — accueil"
>
    <span
        aria-hidden="true"
        class="brand-symbol shrink-0 {{ $large ? 'w-14' : ($vertical ? 'w-7' : 'w-9') }}"
        style="--brand-symbol: url('{{ asset('brand/symbole-128.png') }}')"
    ></span>

    <span class="flex flex-col leading-none {{ $vertical ? 'items-center' : '' }}">
        <span class="font-display {{ $large ? 'text-2xl' : 'text-sm sm:text-base' }} tracking-[0.22em]">AISSATOU</span>

        {{-- Les deux filets encadrant STORE font partie du verrouillage officiel. --}}
        <span class="{{ $large ? 'mt-2 gap-2.5' : 'mt-1 gap-1.5' }} flex items-center">
            <span class="h-px {{ $large ? 'w-6' : 'w-3' }} bg-brand-accent"></span>
            <span class="{{ $large ? 'text-xs' : 'text-[0.5rem]' }} uppercase tracking-[0.35em]">Store</span>
            <span class="h-px {{ $large ? 'w-6' : 'w-3' }} bg-brand-accent"></span>
        </span>

        @if ($withSlogan)
            <span class="{{ $large ? 'mt-2 text-[0.65rem] opacity-80' : 'mt-1 text-[0.4rem] opacity-70' }} uppercase tracking-[0.18em] whitespace-nowrap">L'élégance dans la pudeur</span>
        @endif
    </span>
</a>
