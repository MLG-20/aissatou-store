@props(['disabled' => false])

{{-- Champ mot de passe avec bouton « afficher / masquer » (œil).
     Les classes passées (largeur, marge) vont au conteneur ; les autres
     attributs (id, name, autocomplete, required…) vont au champ. --}}
<div x-data="{ shown: false }" {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <input
        @disabled($disabled)
        x-bind:type="shown ? 'text' : 'password'"
        type="password"
        {{ $attributes->except('class')->merge(['class' => 'block w-full pr-11 border-brand-ink/20 focus:border-brand-signature focus:ring-brand-accent rounded-sm shadow-sm']) }}
    >

    <button
        type="button"
        x-on:click="shown = ! shown"
        x-bind:aria-label="shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
        x-bind:aria-pressed="shown.toString()"
        aria-label="Afficher le mot de passe"
        aria-pressed="false"
        @if ($attributes->has('id')) aria-controls="{{ $attributes->get('id') }}" @endif
        class="absolute inset-y-0 right-0 flex items-center px-3 text-brand-muted hover:text-brand-ink focus:outline-none focus-visible:text-brand-signature"
    >
        {{-- Œil ouvert : le mot de passe est masqué, cliquer l'affiche. --}}
        <svg x-show="! shown" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
        {{-- Œil barré : le mot de passe est visible, cliquer le masque. --}}
        <svg x-show="shown" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>
    </button>
</div>
