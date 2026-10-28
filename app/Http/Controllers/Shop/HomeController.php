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
                // Une vidéo encore en préparation attend son tour.
                ->displayable()
                // Aperçu du Lookbook : les looks choisis pour l'accueil.
                ->where('show_on_home', true)
                ->orderBy('position')
                ->withLivePieces()
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
            // Diaporama de « La Maison » (Réglages du site), sinon la première
            // image du hero.
            'storyImages' => $settings->storyImageUrls()
                ?: array_filter([$slides->first(fn (HeroSlide $slide) => $slide->image_path)?->sizedUrl('large')]),
        ]);
    }

    /**
     * Guide cadeau par budget : trois tranches menant au catalogue filtré par
     * prix. Une tranche vide n'est pas proposée (jamais de page sans résultat).
     *
     * Les seuils ne sont pas figés : ils partagent les prix réels du catalogue
     * en trois parts à peu près égales (arrondies au millier supérieur), et
     * suivent donc d'eux-mêmes les prix que la cliente saisit.
     *
     * @return list<array{label: string, hint: string, count: int, query: array<string, int>}>
     */
    private function giftBudgets(): array
    {
        $prices = Product::query()->active()->orderBy('price')->pluck('price')->map(fn ($price) => (int) $price);

        if ($prices->isEmpty()) {
            return [];
        }

        $threshold = fn (float $share): int => (int) (ceil($prices[(int) ceil($prices->count() * $share) - 1] / 1000) * 1000);
        $low = $threshold(1 / 3);
        $high = $threshold(2 / 3);
        $fcfa = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA';

        return collect([
            ['label' => "Jusqu'à ".$fcfa($low), 'hint' => 'Une attention', 'min' => null, 'max' => $low],
            ['label' => number_format($low, 0, ',', ' ').' à '.$fcfa($high), 'hint' => 'Un vrai cadeau', 'min' => $low + 1, 'max' => $high],
            ['label' => 'Plus de '.$fcfa($high), 'hint' => "L'exception", 'min' => $high + 1, 'max' => null],
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
