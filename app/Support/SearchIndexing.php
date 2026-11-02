<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Ce que les moteurs de recherche ont le droit d'indexer. Liste blanche : une
 * nouvelle page reste hors de Google tant qu'elle n'est pas ajoutée ici, un
 * oubli ne peut donc jamais exposer une page privée.
 */
class SearchIndexing
{
    private const INDEXABLE_ROUTES = [
        'home',
        'shop.index',
        'shop.category',
        'shop.product',
        'shop.collections',
        'shop.collection',
        'shop.lookbook',
        'shop.lookbook.look',
        'shop.journal',
        'shop.post',
        'shop.about',
        'shop.contact',
        'shop.legal',
    ];

    public static function isIndexable(Request $request): bool
    {
        return $request->routeIs(...self::INDEXABLE_ROUTES);
    }

    /**
     * Adresse de référence de la page : sans filtres ni liens suivis (utm…),
     * qui montreraient à Google autant de doublons. Seules les pages suivantes
     * et les Nouveautés, au contenu réellement différent, gardent la leur.
     */
    public static function canonicalUrl(Request $request): string
    {
        $query = array_filter([
            'nouveautes' => $request->boolean('nouveautes') ? 1 : null,
            'page' => $request->integer('page') > 1 ? $request->integer('page') : null,
        ]);

        return $request->url().($query === [] ? '' : '?'.http_build_query($query));
    }
}
