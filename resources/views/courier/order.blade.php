<x-courier-layout :title="'Livraison '.$order->number">
    <p class="text-sm text-brand-muted">Bonjour {{ $courier->name }} 👋</p>

    @include('courier.partials.order-card', ['order' => $order, 'actionUrl' => $actionUrl])
</x-courier-layout>
