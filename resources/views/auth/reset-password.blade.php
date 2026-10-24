<x-guest-layout>
    <h1 class="font-display text-3xl text-brand-ink mb-2">Nouveau mot de passe</h1>
    <p class="text-sm text-brand-muted mb-8">Choisissez un nouveau mot de passe pour votre compte.</p>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-password-input id="password" class="block mt-1 w-full" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmer le mot de passe" />
            <x-password-input id="password_confirmation" class="block mt-1 w-full" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center py-3">
            Réinitialiser le mot de passe
        </x-primary-button>
    </form>
</x-guest-layout>
