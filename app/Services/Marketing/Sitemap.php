<?php

namespace App\Services\Marketing;

use App\Enums\Content\LegalDocument;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Content\LegalPage;
use App\Models\Content\Look;
use App\Models\Content\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use XMLWriter;

/**
 * Plan du site : uniquement ce qu'une visiteuse peut ouvrir, avec les mêmes
 * règles que les contrôleurs (brouillon, produit retiré ou rayon masqué : absents).
 */
class Sitemap
{
    private const CACHE_KEY = 'marketing.sitemap';

    /** Mis en cache une heure : les moteurs ne le relisent pas plus souvent. */
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
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($this->entries() as [$url, $modifiedAt]) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url);
            if ($modifiedAt) {
                $xml->writeElement('lastmod', $modifiedAt->toDateString());
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @return iterable<array{0: string, 1: ?Carbon}> */
    private function entries(): iterable
    {
        foreach (['home', 'shop.index', 'shop.collections', 'shop.lookbook', 'shop.journal', 'shop.about', 'shop.contact'] as $name) {
            yield [route($name), null];
        }

        foreach (Category::query()->active()->orderBy('position')->get() as $category) {
            yield [route('shop.category', $category), $category->updated_at];
        }

        foreach (Collection::query()->active()->orderBy('position')->get() as $collection) {
            yield [route('shop.collection', $collection), $collection->updated_at];
        }

        foreach (Product::query()->active()->latest()->get() as $product) {
            yield [route('shop.product', $product), $product->updated_at];
        }

        foreach (Look::query()->active()->displayable()->orderBy('position')->get() as $look) {
            yield [route('shop.lookbook.look', $look), $look->updated_at];
        }

        foreach (Post::query()->published()->latest('published_at')->get() as $post) {
            yield [route('shop.post', $post), $post->updated_at];
        }

        foreach (LegalPage::query()->published()->get() as $page) {
            /** @var LegalDocument $document */
            $document = $page->document;

            yield [route('shop.legal', $document), $page->updated_at];
        }
    }
}
