<x-filament-panels::page.simple>
    <p class="text-sm text-gray-700 dark:text-gray-300">Saisissez le code à 6 chiffres affiché par votre application. Sans votre téléphone, saisissez l'un de vos codes de secours.</p>

    <form wire:submit="verify" class="space-y-4">
        <x-filament::input.wrapper :valid="! $errors->has('data.code')">
            <x-filament::input type="text" wire:model="data.code" autocomplete="one-time-code" maxlength="11" required autofocus aria-label="Code de sécurité" />
        </x-filament::input.wrapper>
        @error('data.code')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
        <x-filament::button type="submit" class="w-full">Continuer</x-filament::button>
    </form>

    @include('filament.pages.auth.two-factor-logout')
</x-filament-panels::page.simple>
