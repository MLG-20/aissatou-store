<x-filament-widgets::widget>
    @php($rankings = $this->rankings())
    <div class="grid gap-6 md:grid-cols-2">
        @foreach (['quantity' => 'Meilleures ventes — quantités', 'revenue' => 'Meilleures ventes — chiffre d\'affaires'] as $key => $heading)
            <x-filament::section :heading="$heading" description="Commandes payées sur la période, prix figés à l'achat (avant remise).">
                @if (empty($rankings[$key]))
                    <p class="text-sm text-gray-500 dark:text-gray-400">Aucune vente payée sur la période.</p>
                @else
                    <ol class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @foreach ($rankings[$key] as $index => $row)
                            <li class="flex items-center justify-between gap-4 py-2">
                                <span class="min-w-0 truncate"><span class="text-gray-400">{{ $index + 1 }}.</span> {{ $row->product }}</span>
                                <span class="whitespace-nowrap font-semibold">
                                    @if ($key === 'quantity')
                                        {{ $row->quantity }} vendu(s)
                                    @else
                                        {{ number_format($row->revenue, 0, ',', ' ') }} FCFA
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-widgets::widget>
