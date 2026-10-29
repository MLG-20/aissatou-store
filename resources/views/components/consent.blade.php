@props(['category'])
{{-- Contenu soumis à l'accord du visiteur (script de mesure d'audience…) :
     rendu tel quel si la catégorie est acceptée, sinon gardé inerte dans un
     <template> que le bandeau active à l'acceptation (cookie-consent.js).
     Usage : <x-consent category="mesure-audience"><script>…</script></x-consent> --}}
@if (\App\Support\CookieConsent::allows($category))
    {{ $slot }}
@else
    <template data-consent="{{ $category }}">{{ $slot }}</template>
@endif
