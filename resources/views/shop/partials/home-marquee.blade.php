{{-- BANDEAU DÉFILANT sous le hero : les arguments qui lèvent les hésitations
     (paiement à la réception en tête), lus d'un coup d'œil dès l'arrivée
     plutôt qu'au milieu de la page. Texte éditable : Filament > Réglages du
     site > Bandeau défilant. Même mécanique que les nouveautés (piste
     dupliquée, pause au survol, défilable au doigt en animations réduites).

     @var array<string> $items --}}
@if (! empty($items))
    <div class="marquee-viewport overflow-hidden border-b border-brand-surface/10 bg-brand-signature text-brand-parchment">
        <div class="marquee-track flex w-max items-center" style="animation-duration: {{ max(25, count($items) * 9) }}s">
            @foreach ([1, 2] as $repeat)
                <ul @class(['flex items-center', 'marquee-copy' => $repeat === 2]) @if ($repeat === 2) aria-hidden="true" @endif>
                    @foreach ($items as $item)
                        <li class="flex items-center whitespace-nowrap py-3.5 text-[0.75rem] uppercase tracking-[0.3em] sm:text-xs">
                            <span class="px-8 sm:px-12">{{ $item }}</span>
                            <span class="h-1.5 w-1.5 rotate-45 bg-brand-sage" aria-hidden="true"></span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
@endif
