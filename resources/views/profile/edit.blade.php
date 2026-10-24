<x-account-layout title="Mes informations">
    <h2 class="mb-6 font-display text-3xl text-brand-ink">Mes informations</h2>

    <div class="space-y-6 max-w-xl">
        <div class="p-6 sm:p-8 bg-white border border-brand-ink/10 rounded-sm">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="p-6 sm:p-8 bg-white border border-brand-ink/10 rounded-sm">
            @include('profile.partials.update-password-form')
        </div>

        {{-- Zone sensible : léger fond rouge pour signaler la suppression. --}}
        <div class="p-6 sm:p-8 bg-red-50/40 border border-red-200/60 rounded-sm">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-account-layout>
