<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Content\HeroSlide;
use App\Models\Content\Look;
use App\Models\Content\Post;
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

        $collections = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get();

        return view('shop.home', [
            'slides' => $slides,
            'settings' => $settings,
            'collections' => $collections,
            // « Le Look » : seuls les points dont la pièce est encore en ligne.
            'looks' => Look::query()
                ->active()
                ->orderBy('position')
                ->with(['hotspots' => fn ($query) => $query->whereHas('product', fn ($query) => $query->active()),
                    'hotspots.product' => fn ($query) => $query->with(['category', 'images', 'variants' => fn ($query) => $query->active()])])
                ->get()
                ->filter(fn (Look $look) => $look->hotspots->isNotEmpty())
                ->values(),
            'giftCategory' => $collections->firstWhere('slug', 'cadeaux'),
            'giftBudgets' => $this->giftBudgets(),
            'posts' => Post::query()->published()->latest('published_at')->take(3)->get(),
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
            // Photo de « La Maison » : son propre réglage (Réglages du site),
            // sinon la première image du hero.
            'storyImage' => $settings->story_image_path
                ? Storage::disk('public')->url($settings->story_image_path)
                : $slides->first(fn (HeroSlide $slide) => $slide->image_path)?->sizedUrl('large'),
        ]);
    }

    /**
     * Guide cadeau par budget : trois tranches menant au catalogue filtré par
     * prix. Une tranche vide n'est pas proposée (jamais de page sans résultat).
     *
     * @return list<array{label: string, hint: string, count: int, query: array<string, int>}>
     */
    private function giftBudgets(): array
    {
        $prices = Product::query()->active()->pluck('price');

        return collect([
            ['label' => 'Moins de 10 000 FCFA', 'hint' => 'Une attention', 'min' => null, 'max' => 9999],
            ['label' => '10 000 à 25 000 FCFA', 'hint' => 'Un vrai cadeau', 'min' => 10000, 'max' => 25000],
            ['label' => 'Plus de 25 000 FCFA', 'hint' => "L'exception", 'min' => 25001, 'max' => null],
        ])
            ->map(fn (array $budget) => [
                'label' => $budget['label'],
                'hint' => $budget['hint'],
                'count' => $prices->filter(fn ($price) => ($budget['min'] === null || $price >= $budget['min'])
                    && ($budget['max'] === null || $price <= $budget['max']))->count(),
                'query' => array_filter(['min_price' => $budget['min'], 'max_price' => $budget['max']]),
            ])
            ->filter(fn (array $budget) => $budget['count'] > 0)
            ->values()
            ->all();
    }
}
