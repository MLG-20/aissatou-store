<x-account-layout title="Mes commandes">
    <h2 class="mb-6 font-display text-3xl text-brand-ink">Mes commandes</h2>

    @if ($orders->isEmpty())
        <div class="rounded-sm border border-brand-ink/10 bg-white p-10 sm:p-14 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-accent/10 text-brand-signature">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                </svg>
            </div>

            <h2 class="mt-5 font-display text-xl text-brand-ink">Aucune commande pour l'instant</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-brand-muted">
                Votre historique apparaîtra ici dès votre première commande. En attendant, laissez-vous inspirer par nos collections.
            </p>

            <a href="{{ route('shop.index') }}" class="group mt-6 inline-flex items-center gap-2 rounded-sm bg-brand-ink px-6 py-3 text-sm uppercase tracking-wide text-brand-surface transition-all duration-300 hover:bg-brand-accent hover:text-brand-surface">
                Découvrir le catalogue
                <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    @else
        <div class="divide-y divide-brand-ink/10 border-y border-brand-ink/10">
            @foreach ($orders as $order)
                <a href="{{ route('account.orders.show', $order) }}" class="py-4 flex items-center gap-4 hover:bg-brand-parchment/40 transition px-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-brand-ink">{{ $order->number }}</p>
                        <p class="text-xs text-brand-muted mt-0.5">
                            {{ $order->created_at->format('d/m/Y') }} — {{ $order->items_count }} article(s) — {{ $order->status->label() }}
                        </p>
                    </div>
                    <p class="text-sm text-brand-ink whitespace-nowrap">
                        {{ number_format($order->total, 0, ',', ' ') }} FCFA
                    </p>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $orders->links() }}
        </div>
    @endif
</x-account-layout>
