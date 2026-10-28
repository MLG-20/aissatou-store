{{-- Barre du haut du back-office : accès direct à la boutique, dans un nouvel
     onglet pour garder le back-office ouvert à côté. --}}
<x-filament::button
    tag="a"
    href="{{ route('home') }}"
    target="_blank"
    rel="noopener"
    color="gray"
    size="sm"
    icon="heroicon-m-arrow-top-right-on-square"
    icon-position="after"
>
    Voir le site
</x-filament::button>
