<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">Choisissez ce que vous voulez faire : une bulle vous montrera où cliquer, sur le vrai écran.</p>

    @forelse ($this->guides() as $heading => $guides)
        <section>
            <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">{{ $heading }}</h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($guides as $guide)
                    <a href="{{ $guide['url'] }}" class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::icon :icon="$guide['icon']" class="h-8 w-8 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $guide['title'] }}</span>
                            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $guide['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Aucun guide n'est disponible pour votre compte pour le moment.</p>
    @endforelse
</x-filament-panels::page>
