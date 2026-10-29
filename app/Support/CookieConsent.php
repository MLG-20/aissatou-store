<?php

namespace App\Support;

use App\Enums\Content\CookieCategory;
use App\Models\Content\CookieDeclaration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Consentement aux cookies, piloté par le registre de l'admin.
 *
 * Le bandeau ne s'affiche que si au moins un cookie actif du registre exige
 * l'accord du visiteur : tant que le site ne pose que des cookies
 * nécessaires, il reste invisible. Il revient de lui-même quand une nouvelle
 * catégorie apparaît (le visiteur n'a pas encore été interrogé dessus).
 *
 * Le choix vit dans un cookie lisible par le navigateur (non chiffré, cf.
 * bootstrap/app.php) : `{"granted": [...], "asked": [...]}`. Les scripts
 * soumis à accord passent par `<x-consent category="…">`.
 */
class CookieConsent
{
    public const COOKIE = 'aissatou_consent';

    public const CACHE_KEY = 'shop.consent_categories';

    /** Six mois : on redemande ensuite, le choix n'est pas éternel. */
    public const LIFETIME_DAYS = 182;

    /**
     * Catégories soumises à accord ayant au moins un cookie actif.
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): array {
            $active = CookieDeclaration::query()->active()->distinct()->pluck('category')
                ->map(fn (CookieCategory $category) => $category->value)
                ->all();

            return collect(CookieCategory::cases())
                ->filter(fn (CookieCategory $category) => $category->requiresConsent() && in_array($category->value, $active, true))
                ->map(fn (CookieCategory $category) => $category->value)
                ->values()
                ->all();
        });
    }

    /** @return list<string> */
    public static function granted(?Request $request = null): array
    {
        return self::choice($request)['granted'];
    }

    public static function allows(string $category, ?Request $request = null): bool
    {
        return in_array($category, self::granted($request), true);
    }

    public static function shouldAsk(?Request $request = null): bool
    {
        return array_diff(self::categories(), self::choice($request)['asked']) !== [];
    }

    /**
     * @return array{granted: list<string>, asked: list<string>}
     */
    private static function choice(?Request $request): array
    {
        $decoded = json_decode((string) ($request ?? request())->cookie(self::COOKIE), true);

        $clean = fn (mixed $list): array => array_values(array_filter(
            is_array($list) ? $list : [],
            fn (mixed $value) => is_string($value) && CookieCategory::tryFrom($value)?->requiresConsent()
        ));

        return [
            'granted' => $clean($decoded['granted'] ?? null),
            'asked' => $clean($decoded['asked'] ?? null),
        ];
    }
}
