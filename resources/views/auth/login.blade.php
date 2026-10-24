<x-guest-layout>
    <h1 class="font-display text-3xl text-brand-ink mb-2">Bon retour parmi nous</h1>
    <p class="text-sm text-brand-muted mb-8">Connectez-vous pour retrouver votre univers.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-password-input id="password" class="block mt-1 w-full" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-brand-ink/30 text-brand-signature shadow-sm focus:ring-brand-accent" name="remember">
                <span class="ms-2 text-sm text-brand-ink/80">Se souvenir de moi</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-brand-muted hover:text-brand-ink underline" href="{{ route('password.request') }}">
                    Mot de passe oublié ?
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center py-3">
            Se connecter
        </x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-brand-muted">
        Pas encore de compte ?
        <a href="{{ route('register') }}" class="text-brand-ink underline hover:text-brand-signature">Créez-en un</a>
    </p>
</x-guest-layout>
