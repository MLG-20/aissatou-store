<?php

namespace App\Support;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductImage;
use App\Models\Catalogue\ProductVariant;
use App\Models\Content\SiteSetting;
use App\Services\ImageVariantGenerator;
use Illuminate\Support\Str;

/**
 * Données schema.org lues par Google (prix, disponibilité, fil d'Ariane,
 * identité de la boutique). Pas de note en étoiles : les avis du site portent
 * sur la boutique, les présenter comme la note d'un article enfreindrait les
 * règles de Google.
 */
class StructuredData
{
    private const CONTEXT = 'https://schema.org';

    private const CURRENCY = 'XOF';

    /** @return ?array<string, mixed> Rien sans photo : Google refuserait le produit. */
    public static function product(Product $product): ?array
    {
        $images = $product->images->sortByDesc('is_primary')->values()
            ->map(fn (ProductImage $image): string => url($image->sizedUrl(ImageVariantGenerator::SHARE)));

        if ($images->isEmpty()) {
            return null;
        }

        return array_filter([
            '@context' => self::CONTEXT,
            '@type' => 'Product',
            'name' => $product->name,
            'description' => Str::limit(trim(strip_tags((string) $product->description)), 4900),
            'sku' => $product->sku,
            'image' => $images->all(),
            'brand' => ['@type' => 'Brand', 'name' => config('app.name')],
            'offers' => self::offers($product),
        ]);
    }

    /** @return array<string, mixed> */
    private static function offers(Product $product): array
    {
        $prices = $product->variants->toBase()
            ->map(fn (ProductVariant $variant): int => (int) ($variant->price_override ?? $product->price))
            ->whenEmpty(fn ($prices) => $prices->push((int) $product->price));

        $common = [
            'priceCurrency' => self::CURRENCY,
            'availability' => 'https://schema.org/'.($product->isInStock() ? 'InStock' : 'OutOfStock'),
            'url' => route('shop.product', $product),
        ];

        if ($prices->unique()->count() === 1) {
            return ['@type' => 'Offer', 'price' => $prices->first(), 'itemCondition' => 'https://schema.org/NewCondition'] + $common;
        }

        return ['@type' => 'AggregateOffer', 'lowPrice' => $prices->min(), 'highPrice' => $prices->max(), 'offerCount' => $prices->count()] + $common;
    }

    /**
     * @param  ?array{0: string, 1: string}  $last  Dernière étape après le rayon (nom, adresse)
     * @return array<string, mixed>
     */
    public static function breadcrumb(?Category $category, ?array $last = null): array
    {
        $trail = [];
        for ($current = $category; $current; $current = $current->parent) {
            array_unshift($trail, [$current->name, route('shop.category', $current)]);
        }

        $steps = [['Accueil', route('home')], ...$trail, ...($last ? [$last] : [])];

        return [
            '@context' => self::CONTEXT,
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $step, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $step[0],
                'item' => $step[1],
            ], $steps, array_keys($steps)),
        ];
    }

    /** @return list<array<string, mixed>> La boutique, et le nom du site affiché par Google. */
    public static function shop(SiteSetting $settings): array
    {
        // Numéro de démonstration (« +221 XX XXX XX XX ») : jamais annoncé.
        $digits = preg_replace('/\D/', '', (string) $settings->contact_whatsapp);

        return [
            array_filter([
                '@context' => self::CONTEXT,
                '@type' => 'OnlineStore',
                'name' => config('app.name'),
                'url' => route('home'),
                'logo' => url('/brand/symbole-512.png'),
                'image' => url('/brand/partage.jpg'),
                'description' => $settings->seo_home_description,
                'email' => $settings->contact_email,
                'telephone' => strlen($digits) >= 9 ? '+'.$digits : null,
                'address' => $settings->contact_address,
                'sameAs' => array_values(array_filter([$settings->social_instagram, $settings->social_facebook, $settings->social_tiktok])),
            ]),
            [
                '@context' => self::CONTEXT,
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => route('home'),
            ],
        ];
    }

    /** Sans danger dans une balise script : un nom saisi en admin ne peut pas la refermer. */
    public static function encode(array $block): string
    {
        return json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
    }
}
