<x-account-layout title="Mes adresses">
    <h2 class="mb-6 font-display text-3xl text-brand-ink">Mes adresses</h2>

    <div class="grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="text-lg font-medium text-brand-ink mb-4">Adresses enregistrées</h2>

            @if (session('status') === 'address-created')
                <p class="text-sm text-green-700 mb-4">Adresse ajoutée.</p>
            @elseif (session('status') === 'address-updated')
                <p class="text-sm text-green-700 mb-4">Adresse mise à jour.</p>
            @elseif (session('status') === 'address-deleted')
                <p class="text-sm text-green-700 mb-4">Adresse supprimée.</p>
            @endif

            @forelse ($addresses as $address)
                <div class="p-5 mb-4 bg-white border {{ $address->is_default ? 'border-brand-signature/40' : 'border-brand-ink/10' }} rounded-sm transition hover:shadow-md hover:border-brand-signature/40" x-data="{ editing: false }">
                    <div x-show="!editing">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-brand-ink">
                                    {{ $address->recipient_name }}
                                    @if ($address->is_default)
                                        <span class="ml-2 text-xs uppercase tracking-wide text-brand-signature border border-brand-signature rounded-sm px-2 py-0.5">Par défaut</span>
                                    @endif
                                </p>
                                <p class="text-sm text-brand-ink/80 mt-1">{{ $address->phone }}</p>
                                <p class="text-sm text-brand-ink/80">{{ $address->address_line }}, {{ $address->city }}</p>
                                @if ($address->landmark)
                                    <p class="text-sm text-brand-muted">Repère : {{ $address->landmark }}</p>
                                @endif
                            </div>

                            <div class="flex flex-col gap-2 items-end shrink-0">
                                <button type="button" x-on:click="editing = true" class="text-sm underline text-brand-muted hover:text-brand-ink">
                                    Modifier
                                </button>

                                <form method="post" action="{{ route('account.addresses.destroy', $address) }}" onsubmit="return confirm('Supprimer cette adresse ?');">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="text-sm underline text-red-600 hover:text-red-800">Supprimer</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <form x-show="editing" x-cloak method="post" action="{{ route('account.addresses.update', $address) }}" class="space-y-4">
                        @csrf
                        @method('put')
                        @include('account.partials.address-fields', ['address' => $address])

                        <div class="flex items-center gap-3">
                            <x-primary-button>Enregistrer</x-primary-button>
                            <button type="button" x-on:click="editing = false" class="text-sm text-brand-muted hover:text-brand-ink">Annuler</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="rounded-sm border border-dashed border-brand-ink/20 p-8 text-center text-sm text-brand-muted">
                    Vous n'avez pas encore enregistré d'adresse.<br>
                    Ajoutez-en une pour accélérer vos prochaines commandes.
                </div>
            @endforelse
        </div>

        <div>
            <h2 class="text-lg font-medium text-brand-ink mb-4">Ajouter une adresse</h2>

            <form method="post" action="{{ route('account.addresses.store') }}" class="p-6 bg-white border border-brand-ink/10 rounded-sm space-y-4">
                @csrf
                {{-- null explicite : sinon la dernière adresse de la boucle ci-dessus pré-remplit l'ajout. --}}
                @include('account.partials.address-fields', ['address' => null])

                <x-primary-button>Ajouter l'adresse</x-primary-button>
            </form>
        </div>
    </div>
</x-account-layout>
