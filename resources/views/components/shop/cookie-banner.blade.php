@php
    use App\Enums\Content\CookieCategory;
    use App\Enums\Content\LegalDocument;
    use App\Models\Content\LegalPage;
    use App\Support\CookieConsent;

    $categories = CookieConsent::categories();
    $cookiesPage = LegalPage::for(LegalDocument::Cookies);
@endphp

{{-- Bandeau de consentement : absent tant que le registre (admin > Cookies et
     traceurs) ne contient que des cookies nécessaires. « Tout refuser » pèse
     autant que « Tout accepter » : refuser doit être aussi simple qu'accepter. --}}
@if ($categories)
    <div
        x-data="cookieConsent(@js(['categories' => $categories, 'granted' => CookieConsent::granted(), 'open' => CookieConsent::shouldAsk()]))"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.300ms
        role="dialog"
        aria-labelledby="cookie-banner-title"
        class="fixed inset-x-4 bottom-4 z-50 border border-brand-ink/10 bg-brand-surface p-6 shadow-[0_18px_40px_-12px_rgba(23,21,27,0.35)] sm:inset-x-auto sm:left-6 sm:bottom-6 sm:max-w-md"
    >
        <p id="cookie-banner-title" class="font-display text-2xl text-brand-ink">Vos cookies, votre choix</p>
        <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
            Certains cookies sont indispensables au site. D'autres nous aident à l'améliorer : ils ne sont déposés qu'avec votre accord, que vous pouvez retirer à tout moment.
            @if ($cookiesPage?->is_published)
                <a href="{{ route('shop.legal', LegalDocument::Cookies) }}" class="text-brand-signature underline underline-offset-4">En savoir plus</a>
            @endif
        </p>

        <div x-show="customizing" x-cloak class="mt-5 space-y-4 border-t border-brand-ink/10 pt-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-brand-ink">{{ CookieCategory::Necessary->label() }}</p>
                    <p class="text-xs text-brand-muted">Toujours actifs</p>
                </div>
            </div>
            @foreach ($categories as $value)
                @php($category = CookieCategory::from($value))
                <label class="flex cursor-pointer items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-brand-ink">{{ $category->label() }}</span>
                        <span class="block text-xs text-brand-muted">{{ $category->description() }}</span>
                    </span>
                    <input type="checkbox" x-model="choices['{{ $value }}']" class="mt-1 h-4 w-4 rounded-none border-brand-ink/40 text-brand-ink focus:ring-brand-ink">
                </label>
            @endforeach
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <button type="button" @click="rejectAll()" class="border border-brand-ink px-4 py-3 text-xs font-medium uppercase tracking-[0.15em] text-brand-ink transition hover:bg-brand-ink hover:text-brand-surface">
                Tout refuser
            </button>
            <button type="button" @click="acceptAll()" class="border border-brand-ink bg-brand-ink px-4 py-3 text-xs font-medium uppercase tracking-[0.15em] text-brand-surface transition hover:bg-brand-accent hover:border-brand-accent">
                Tout accepter
            </button>
        </div>
        <button type="button" x-show="!customizing" @click="customizing = true" class="mt-4 text-xs uppercase tracking-[0.15em] text-brand-muted underline underline-offset-4 hover:text-brand-ink">
            Personnaliser
        </button>
        <button type="button" x-show="customizing" x-cloak @click="saveChoices()" class="mt-4 text-xs uppercase tracking-[0.15em] text-brand-ink underline underline-offset-4 hover:text-brand-signature">
            Enregistrer mes choix
        </button>
    </div>
@endif
