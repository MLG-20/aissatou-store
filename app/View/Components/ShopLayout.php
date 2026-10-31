<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ShopLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        // Header transparent posé sur le hero (accueil), qui devient opaque au
        // scroll. Les pages sans hero sombre gardent le header opaque classique.
        public bool $transparentHeader = false,
        // Aperçu d'un lien partagé (WhatsApp, Instagram…) : texte et image.
        // Sans image, celle de la marque ; sans description, la signature.
        public ?string $description = null,
        public ?string $image = null,
        // Titre de l'aperçu, s'il diffère de l'onglet (fiche produit : le prix).
        public ?string $shareTitle = null,
    ) {}

    public function render(): View
    {
        return view('layouts.shop');
    }
}
