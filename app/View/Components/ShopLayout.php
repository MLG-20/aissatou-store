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
        public ?string $description = null,
        public ?string $image = null,
    ) {}

    public function render(): View
    {
        return view('layouts.shop');
    }
}
