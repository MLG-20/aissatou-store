<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductImage;
use App\Models\Content\HeroSlide;
use App\Models\Content\Look;
use App\Models\Content\Post;
use App\Models\Content\SiteSetting;
use App\Observers\CategoryObserver;
use App\Observers\CollectionObserver;
use App\Observers\HeroSlideObserver;
use App\Observers\LookObserver;
use App\Observers\PostObserver;
use App\Observers\ProductImageObserver;
use App\Services\AdminAudit;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductImage::observe(ProductImageObserver::class);
        HeroSlide::observe(HeroSlideObserver::class);
        Look::observe(LookObserver::class);
        Category::observe(CategoryObserver::class);
        Collection::observe(CollectionObserver::class);
        Post::observe(PostObserver::class);

        // Journal d'activité admin (Phase 6A) : connexions au back-office,
        // réussies ou non (un échec ne connaît que l'email tenté).
        Event::listen(function (Login $event): void {
            if ($event->guard === 'admin' && $event->user instanceof Admin) {
                AdminAudit::record('admin.login', $event->user, admin: $event->user);
            }
        });
        Event::listen(function (Failed $event): void {
            if ($event->guard === 'admin') {
                AdminAudit::record('admin.login_failed', details: ['email' => $event->credentials['email'] ?? null]);
            }
        });

        // Toutes les pages boutique partagent la même nav de catégories racines ;
        // ce composer évite de passer $navCategories manuellement depuis chaque
        // contrôleur Shop\*.
        View::composer('layouts.shop', function ($view): void {
            $cart = app(CartService::class)->currentCartForDisplay(request());

            $view->with('navCategories', $this->navCategories());
            $view->with('headerCart', $cart);
            $view->with('cartItemCount', $cart ? $cart->items->sum('quantity') : 0);
            $view->with('wishlistCount', count(app(WishlistService::class)->currentProductIds(request())));
            $view->with('footerSettings', SiteSetting::current());
        });
    }

    /**
     * Catégories racines (avec leurs sous-catégories, pour le menu déroulant
     * Catalogue du header) pour la nav boutique, en cache.
     *
     * @return array<int, array{name: string, slug: string, children: array<int, array{name: string, slug: string}>}>
     */
    private function navCategories(): array
    {
        // On cache un tableau de tableaux, pas une Collection de modèles
        // Eloquent : `config('cache.serializable_classes')` vaut `false` par
        // défaut dans ce projet (durcissement sécurité de Laravel contre
        // l'injection d'objets PHP via le cache — voir config/cache.php),
        // donc désérialiser un modèle depuis le cache retombe silencieusement
        // sur un `__PHP_Incomplete_Class`. Un tableau simple n'a pas ce
        // problème et suffit pour ce que la nav affiche (nom + slug).
        return Cache::remember(
            'shop.nav_categories',
            now()->addHour(),
            fn () => Category::query()
                ->active()
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->active()->orderBy('position')])
                ->orderBy('position')
                ->get()
                ->map(fn (Category $category) => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'children' => $category->children
                        ->map(fn (Category $child) => ['name' => $child->name, 'slug' => $child->slug])
                        ->all(),
                ])
                ->all()
        );
    }
}
