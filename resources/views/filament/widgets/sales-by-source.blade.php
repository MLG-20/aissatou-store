<x-filament-widgets::widget>
    @php($rows = $this->rows())
    <x-filament::section heading="Ventes par source" description="D'où viennent vos clientes sur la période. « Non renseignée » : arrivée sans lien de suivi, ou commande passée plus tard, après une nouvelle visite.">
        @if (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune visite ni commande sur la période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">Source</th>
                            <th class="px-4 py-2 text-right font-medium">Visites</th>
                            <th class="px-4 py-2 text-right font-medium">Commandes</th>
                            <th class="px-4 py-2 text-right font-medium">Payées</th>
                            <th class="py-2 pl-4 text-right font-medium">Chiffre d'affaires</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row->source }}</td>
                                <td class="px-4 py-2 text-right">{{ $row->visitors }}</td>
                                <td class="px-4 py-2 text-right">{{ $row->placed_orders }}</td>
                                <td class="px-4 py-2 text-right">{{ $row->paid_orders }}</td>
                                <td class="whitespace-nowrap py-2 pl-4 text-right font-semibold">{{ number_format($row->revenue, 0, ',', ' ') }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
