<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Content\Look;
use App\Models\Content\SiteSetting;
use Illuminate\View\View;

/**
 * Le Lookbook : tous les looks en plein écran, l'un après l'autre. Chaque
 * look a son adresse (/lookbook/lumiere-doree) : la page s'ouvre directement
 * dessus, et le lien partagé montre son image d'aperçu.
 */
class LookbookController extends Controller
{
    public function index(): View
    {
        return $this->page();
    }

    public function show(Look $look): View
    {
        // Masqué, ou vidéo encore en préparation : pas encore publié.
        abort_unless(Look::query()->active()->displayable()->whereKey($look->id)->exists(), 404);

        return $this->page($look);
    }

    private function page(?Look $current = null): View
    {
        return view('shop.lookbook', [
            'looks' => Look::query()->active()->displayable()->orderBy('position')->withLivePieces()->get(),
            'current' => $current,
            'settings' => SiteSetting::current(),
        ]);
    }
}
