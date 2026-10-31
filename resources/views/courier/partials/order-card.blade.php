{{-- Une commande vue par le livreur. Une fois terminée, plus aucune donnée
     de la cliente : le lien peut traîner dans un téléphone. --}}
@php
    $service = app(\App\Services\Delivery\CourierService::class);
    $done = in_array($order->status, [\App\Enums\Order\OrderStatus::Delivered, \App\Enums\Order\OrderStatus::Cancelled], true);
    $phone = \App\Support\PhoneNumber::toE164($order->phone);
    $amount = $done ? null : $service->amountToCollect($order);
@endphp

<article class="rounded-sm border border-brand-ink/10 bg-white">
    <div class="flex items-center justify-between gap-3 border-b border-brand-ink/10 px-4 py-3">
        <p class="font-medium">{{ $order->number }}</p>
        <span class="rounded-full bg-brand-ink/5 px-3 py-1 text-xs uppercase tracking-wide">{{ $order->status->label() }}</span>
    </div>

    @if ($done)
        <p class="px-4 py-6 text-center text-sm text-brand-muted">
            Commande terminée, merci !<br>Les coordonnées de la cliente ne sont plus affichées.
        </p>
    @else
        <div class="space-y-4 px-4 py-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-brand-muted">Cliente</p>
                <p class="mt-1 text-base">{{ $order->recipient_name }}</p>
                <div class="mt-2 grid gap-2 {{ $phone ? 'grid-cols-2' : '' }}">
                    <a href="tel:{{ $phone ?? $order->phone }}" class="rounded-sm border border-brand-ink/20 py-2.5 text-center text-sm">📞 Appeler</a>
                    @if ($phone)
                        <a href="https://wa.me/{{ ltrim($phone, '+') }}" class="rounded-sm border border-brand-ink/20 py-2.5 text-center text-sm">💬 WhatsApp</a>
                    @endif
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-wide text-brand-muted">Adresse</p>
                <p class="mt-1 text-sm">{{ $order->address_line }}, {{ $order->city }}</p>
                @if ($order->landmark)
                    <p class="text-sm text-brand-muted">Repère : {{ $order->landmark }}</p>
                @endif
                <a href="{{ $service->directionsUrl($order) }}" target="_blank" rel="noopener noreferrer"
                   class="mt-2 block rounded-sm border border-brand-ink/20 py-2.5 text-center text-sm">
                    🧭 Itinéraire{{ $order->hasLocation() ? ' (position exacte)' : '' }}
                </a>
            </div>

            <div>
                <p class="text-xs uppercase tracking-wide text-brand-muted">Colis</p>
                <ul class="mt-1 text-sm">
                    @foreach ($order->items as $item)
                        <li>{{ $item->quantity }} × {{ $item->product_name }}{{ $item->variant_label ? ' ('.$item->variant_label.')' : '' }}</li>
                    @endforeach
                </ul>
            </div>

            <p class="rounded-sm px-3 py-2 text-sm {{ $amount !== null ? 'bg-amber-50 text-amber-900' : 'bg-green-50 text-green-800' }}">
                {{ $amount !== null ? '💰 À encaisser : '.number_format($amount, 0, ',', ' ').' FCFA' : '✅ Déjà payée : rien à encaisser' }}
            </p>

            <form method="POST" action="{{ $actionUrl }}"
                  onsubmit="return confirm(this.action_label.value)">
                @csrf
                @if ($order->status === \App\Enums\Order\OrderStatus::Preparing)
                    <input type="hidden" name="action" value="depart">
                    <input type="hidden" name="action_label" value="Vous avez bien le colis et vous partez ?">
                    <button class="w-full rounded-sm bg-brand-ink py-4 text-base font-medium text-white">📦 J'ai le colis, je pars</button>
                @elseif ($order->status === \App\Enums\Order\OrderStatus::Shipped)
                    <input type="hidden" name="action" value="livree">
                    <input type="hidden" name="action_label" value="Confirmer la livraison de {{ $order->number }} ?">
                    <button class="w-full rounded-sm bg-green-700 py-4 text-base font-medium text-white">✅ Livrée</button>
                @else
                    <p class="text-center text-sm text-brand-muted">La boutique prépare encore cette commande.</p>
                @endif
            </form>
        </div>
    @endif
</article>
