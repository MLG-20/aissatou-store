<x-filament-panels::page.simple>
    @if ($recoveryCodes === null)
        <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
            <p><strong>1.</strong> Installez sur votre téléphone une application gratuite de codes, comme Google Authenticator.</p>
            <p><strong>2.</strong> Dans l'application, scannez ce QR code.</p>

            <div class="flex justify-center rounded-lg bg-white p-2">{!! $qrCode !!}</div>

            <details>
                <summary class="cursor-pointer">Impossible de scanner ? Saisissez cette clé à la main</summary>
                <code class="mt-2 block break-all select-all">{{ $secret }}</code>
            </details>

            <p><strong>3.</strong> Saisissez le code à 6 chiffres affiché par l'application.</p>
        </div>

        <form wire:submit="confirm" class="space-y-4">
            <x-filament::input.wrapper :valid="! $errors->has('data.code')">
                <x-filament::input type="text" wire:model="data.code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus aria-label="Code à 6 chiffres" />
            </x-filament::input.wrapper>
            @error('data.code')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
            <x-filament::button type="submit" class="w-full">Activer la protection</x-filament::button>
        </form>
    @else
        <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
            <p><strong>La protection est active.</strong> Voici vos codes de secours : ils remplacent le téléphone en cas de perte ou de panne. Chacun ne sert qu'une fois.</p>
            <p class="font-semibold text-danger-600 dark:text-danger-400">Notez-les maintenant et rangez-les en lieu sûr, hors du téléphone. Ils ne seront plus jamais affichés.</p>

            <ul class="grid grid-cols-2 gap-2 rounded-lg bg-gray-100 p-4 font-mono dark:bg-white/5">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>

            <x-filament::button wire:click="finish" class="w-full">J'ai noté mes codes, continuer</x-filament::button>
        </div>
    @endif

    @include('filament.pages.auth.two-factor-logout')
</x-filament-panels::page.simple>
