<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-brand-ink">
            Supprimer le compte
        </h2>

        <p class="mt-1 text-sm text-brand-muted">
            Une fois votre compte supprimé, toutes ses données seront définitivement effacées. Avant de supprimer votre compte, téléchargez toute donnée que vous souhaitez conserver.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Supprimer le compte</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-brand-ink">
                Êtes-vous sûr(e) de vouloir supprimer votre compte ?
            </h2>

            <p class="mt-1 text-sm text-brand-muted">
                Une fois votre compte supprimé, toutes ses données seront définitivement effacées. Saisissez votre mot de passe pour confirmer la suppression définitive de votre compte.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Mot de passe" class="sr-only" />

                <x-password-input
                    id="password"
                    name="password"
                    class="mt-1 block w-3/4"
                    placeholder="Mot de passe"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Annuler
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    Supprimer le compte
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
