<x-guest-layout>
    <h1 class="font-display text-3xl text-brand-ink mb-2">Confirmer le mot de passe</h1>
    <p class="text-sm text-brand-muted mb-8">
        Ceci est une zone sécurisée. Confirmez votre mot de passe avant de continuer.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-password-input id="password" class="block mt-1 w-full" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center py-3">
            Confirmer
        </x-primary-button>
    </form>
</x-guest-layout>
