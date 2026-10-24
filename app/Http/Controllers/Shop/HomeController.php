<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Content\HeroSlide;
use App\Models\Content\Review;
use App\Models\Content\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $slides = HeroSlide::query()->active()->orderBy('position')->get();
        $settings = SiteSetting::current();
        $reviews = Review::query()
            ->published()
            ->orderBy('position')
            ->latest()
            ->get();

        return view('shop.home', [
            'slides' => $slides,
            'settings' => $settings,
            'collections' => Category::query()
                ->active()
                ->whereNull('parent_id')
                ->orderBy('position')
                ->get(),
            // Nouveautés = produits marqués « Nouveauté » en admin (cf.
            // Product::scopeNewArrivals). Auparavant `latest()->take(8)`, qui
            // affichait les dernières lignes créées : comme tout le catalogue a
            // été importé d'un coup, la section montrait des sacs et des parfums
            // au hasard de l'`id`, et le marquage fait en admin restait sans
            // effet. La section se masque désormais d'elle-même si rien n'est
            // marqué, plutôt que d'annoncer de fausses nouveautés.
            'newProducts' => Product::query()
                ->active()
                ->newArrivals()
                ->with(['category', 'images', 'variants' => fn ($query) => $query->active()])
                ->latest()
                ->take(8)
                ->get(),
            'reviews' => $reviews,
            // Note moyenne des avis notés (la note est facultative).
            'reviewsAverage' => $reviews->whereNotNull('rating')->avg('rating'),
            'reviewsRated' => $reviews->whereNotNull('rating')->count(),
            // Photo de « Notre histoire » : pas de réglage dédié, on reprend
            // la photo de marque du panneau de connexion, sinon la première
            // image du hero.
            'storyImage' => $settings->auth_image_path
                ? Storage::disk('public')->url($settings->auth_image_path)
                : $slides->first(fn (HeroSlide $slide) => $slide->image_path)?->sizedUrl('large'),
        ]);
    }
}
