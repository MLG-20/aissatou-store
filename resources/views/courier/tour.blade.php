<x-courier-layout title="Ma tournée">
    <div class="flex items-end justify-between gap-3">
        <div>
            <p class="text-sm text-brand-muted">Bonjour {{ $courier->name }} 👋</p>
            <h1 class="font-display text-2xl">Ma tournée</h1>
        </div>
        @if ($deliveredToday)
            <p class="text-sm text-green-800">✅ {{ $deliveredToday }} livrée{{ $deliveredToday > 1 ? 's' : '' }} aujourd'hui</p>
        @endif
    </div>

    @forelse ($orders as $order)
        @include('courier.partials.order-card', [
            'order' => $order,
            'actionUrl' => route('courier.tour.update', [$token, $order->number], absolute: false),
        ])
    @empty
        <p class="rounded-sm border border-brand-ink/10 bg-white px-4 py-8 text-center text-sm text-brand-muted">
            Aucune livraison en cours pour le moment.<br>Gardez cette page : vos prochaines commandes apparaîtront ici.
        </p>
    @endforelse
</x-courier-layout>
