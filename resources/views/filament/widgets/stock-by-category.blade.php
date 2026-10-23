<x-filament-widgets::widget>
    <x-filament::section heading="Vue d'ensemble par catégorie" description="Déclinaisons en vente uniquement.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-2 pr-4 font-medium">Catégorie</th>
                        <th class="py-2 pr-4 font-medium text-right">Déclinaisons</th>
                        <th class="py-2 pr-4 font-medium text-right">Unités en stock</th>
                        <th class="py-2 pr-4 font-medium text-right">Stock faible</th>
                        <th class="py-2 font-medium text-right">Rupture</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->rows() as $row)
                        <tr>
                            <td class="py-2 pr-4">{{ $row->category }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row->variants }}</td>
                            <td class="py-2 pr-4 text-right font-semibold">{{ number_format($row->units, 0, ',', ' ') }}</td>
                            <td class="py-2 pr-4 text-right {{ $row->low > 0 ? 'text-warning-600 font-semibold' : '' }}">{{ $row->low }}</td>
                            <td class="py-2 text-right {{ $row->out > 0 ? 'text-danger-600 font-semibold' : '' }}">{{ $row->out }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">Aucune déclinaison en vente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
