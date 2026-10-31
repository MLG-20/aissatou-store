<?php

namespace App\Services\Marketing;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductImage;
use App\Models\Catalogue\ProductVariant;
use App\Services\ImageVariantGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * Flux catalogue au format Google Merchant (RSS 2.0, espace `g:`), lu aussi
 * par Meta Commerce Manager : tag des produits dans les posts Instagram et
 * Facebook, onglet Shopping de Google. Une ligne par déclinaison, regroupées
 * par produit ; uniquement des données publiques du catalogue.
 */
class ProductFeed
{
    private const CACHE_KEY = 'marketing.product_feed';

    private const BRAND = 'Aissatou Store';

    /** Photos en plus de la principale (limite Google et Meta : 10). */
    private const EXTRA_IMAGES = 9;

    /**
     * Catégorie Google quand elle est évidente, par catégorie racine du site.
     * « Cadeaux » mêle tout : laissée à Google.
     */
    private const GOOGLE_CATEGORIES = [
        'hijabs' => 'Apparel & Accessories > Clothing Accessories > Scarves & Shawls',
        'foulards' => 'Apparel & Accessories > Clothing Accessories > Scarves & Shawls',
        'cols' => 'Apparel & Accessories > Clothing Accessories > Scarves & Shawls',
        'parfums' => 'Health & Beauty > Personal Care > Cosmetics > Perfume & Cologne',
        'sacs-a-main' => 'Apparel & Accessories > Handbags, Wallets & Cases > Handbags',
    ];

    /** Mis en cache une heure : Meta et Google le relisent au plus chaque heure. */
    public function xml(): string
    {
        return Cache::remember(self::CACHE_KEY.'.'.md5(url('/')), now()->addHour(), fn (): string => $this->build());
    }

    private function build(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $xml->startElement('channel');
        $xml->writeElement('title', self::BRAND);
        $xml->writeElement('link', url('/'));
        $xml->writeElement('description', 'Catalogue '.self::BRAND);

        Product::active()
            ->with(['category.parent', 'images', 'variants' => fn ($query) => $query->active()])
            ->orderBy('id')
            ->each(function (Product $product) use ($xml): void {
                foreach ($this->items($product) as $item) {
                    $xml->startElement('item');
                    foreach ($item as $name => $value) {
                        foreach ((array) $value as $one) {
                            $xml->writeElement($name, (string) $one);
                        }
                    }
                    $xml->endElement();
                }
            });

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @return list<array<string, string|list<string>>> Une entrée par déclinaison active, ou une seule. */
    private function items(Product $product): array
    {
        $images = $product->images->sortByDesc('is_primary')->values()
            ->map(fn (ProductImage $image): string => url($image->sizedUrl(ImageVariantGenerator::SHARE)));

        if ($images->isEmpty()) {
            return []; // Refusé par Meta et Google sans photo.
        }

        $base = array_filter([
            'g:link' => route('shop.product', $product),
            'g:image_link' => $images->first(),
            'g:additional_image_link' => $images->slice(1, self::EXTRA_IMAGES)->values()->all(),
            'g:brand' => self::BRAND,
            'g:condition' => 'new',
            'g:google_product_category' => self::GOOGLE_CATEGORIES[$this->rootSlug($product->category)] ?? null,
            'g:description' => Str::limit(trim(strip_tags((string) $product->description)), 4900) ?: $product->name,
        ]);

        if ($product->variants->isEmpty()) {
            // Sans déclinaison, pas de suivi de stock : toujours disponible.
            return [[
                'g:id' => $product->sku ?: 'P'.$product->id,
                'g:title' => $product->name,
                'g:availability' => 'in_stock',
                'g:price' => $this->price($product->price),
            ] + $base];
        }

        return $product->variants->map(fn (ProductVariant $variant): array => array_filter([
            'g:id' => $variant->sku ?: 'V'.$variant->id,
            'g:item_group_id' => $product->sku ?: 'P'.$product->id,
            'g:title' => $this->variantTitle($product, $variant),
            'g:availability' => $variant->stock > 0 ? 'in_stock' : 'out_of_stock',
            'g:price' => $this->price($variant->price_override ?? $product->price),
            'g:color' => $variant->colourValue(),
        ]) + $base)->all();
    }

    /**
     * « Voile Mousseline Bordeaux », « Parfum Oud 50 ml » : le nom et les
     * valeurs de la déclinaison, sans répéter un mot déjà dans le nom.
     */
    private function variantTitle(Product $product, ProductVariant $variant): string
    {
        $values = collect($variant->getAttribute('attributes') ?? [])
            ->filter(fn ($value) => is_string($value) && ! Str::contains($product->name, $value, ignoreCase: true));

        return trim($product->name.' '.$values->implode(' '));
    }

    private function price(mixed $amount): string
    {
        return number_format((float) $amount, 0, '.', '').' XOF';
    }

    private function rootSlug(?Category $category): ?string
    {
        return $category?->parent?->slug ?? $category?->slug;
    }
}
