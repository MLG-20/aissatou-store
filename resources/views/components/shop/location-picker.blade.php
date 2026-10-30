{{-- Position exacte de livraison, facultative (resources/js/location-picker.js).
     Carnet d'adresses : GPS ou lien Maps. Tunnel de commande : GPS seul,
     pour une adresse qui n'en a pas encore. --}}
@props(['address' => null, 'withLink' => true])

<div
    x-data="locationPicker({{ Js::from([
        'lat' => old('latitude', $address?->latitude),
        'lng' => old('longitude', $address?->longitude),
        'accuracy' => old('location_accuracy', $address?->location_accuracy),
    ]) }})"
    {{ $attributes->class('space-y-2') }}
>
    <input type="hidden" name="latitude" :value="lat ?? ''">
    <input type="hidden" name="longitude" :value="lng ?? ''">
    <input type="hidden" name="location_accuracy" :value="accuracy ?? ''">

    <button
        type="button"
        x-show="lat === null"
        x-on:click="locate()"
        x-bind:disabled="locating"
        class="inline-flex items-center gap-2 rounded-sm border border-brand-ink/30 px-4 py-2.5 text-sm text-brand-ink hover:border-brand-signature disabled:opacity-60"
    >
        <span aria-hidden="true">📍</span>
        <span x-text="locating ? 'Recherche de votre position…' : 'Utiliser ma position actuelle'">Utiliser ma position actuelle</span>
    </button>
    <p x-show="lat === null" class="text-xs text-brand-muted">
        À faire depuis le lieu de livraison : le livreur arrivera directement à la bonne porte.
    </p>
    <p x-show="error" x-text="error" x-cloak class="text-sm text-brand-signature"></p>

    <div x-show="lat !== null" x-cloak class="space-y-2">
        <div x-ref="map" class="isolate h-56 w-full rounded-sm border border-brand-ink/10"></div>
        <p class="text-xs text-brand-muted">Déplacez le repère si besoin, pour le poser exactement sur l'entrée.</p>
        <p x-show="imprecise" class="text-xs text-brand-signature">
            Position approximative (à <span x-text="accuracy"></span> m près) : vérifiez le repère sur la carte.
        </p>
        <button type="button" x-on:click="clear()" class="text-sm underline text-brand-muted hover:text-brand-signature">
            Retirer la position
        </button>
    </div>
    <x-input-error :messages="array_merge($errors->get('latitude'), $errors->get('longitude'))" />

    @if ($withLink)
        <div class="pt-2">
            <x-input-label value="Ou collez un lien Google Maps (facultatif)" />
            <x-text-input name="maps_link" type="url" class="mt-1 block w-full" :value="old('maps_link', $address?->maps_link)" placeholder="https://maps.app.goo.gl/…" />
            <x-input-error class="mt-2" :messages="$errors->get('maps_link')" />
        </div>
    @endif
</div>
