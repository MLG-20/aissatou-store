@props(['order'])

{{-- Frise de suivi d'une commande : quatre étapes avec icône, et la date réelle
     de chacune quand elle est franchie (historique des statuts). L'étape en
     cours est mise en avant. Commande annulée : message à la place. --}}
@use('App\Enums\Order\OrderStatus')
@php
    $steps = [
        ['status' => OrderStatus::New, 'label' => 'Reçue', 'icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        ['status' => OrderStatus::Preparing, 'label' => 'En préparation', 'icon' => 'm20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z'],
        ['status' => OrderStatus::Shipped, 'label' => 'En route', 'icon' => 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v11.177m0-11.177L12 4.5m2.25 3.073L18 4.5m-3.75 3.073v11.177'],
        ['status' => OrderStatus::Delivered, 'label' => 'Livrée', 'icon' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z'],
    ];

    $currentIndex = array_search($order->status, array_column($steps, 'status'), true);
    $reachedAt = $order->history->mapWithKeys(fn ($entry) => [$entry->to_status => $entry->created_at]);
@endphp

@if ($order->status === OrderStatus::Cancelled)
    <p {{ $attributes->merge(['class' => 'border-l-2 border-brand-accent bg-brand-parchment/60 px-4 py-3 text-sm text-brand-ink']) }}>
        Cette commande a été annulée. Une question ? Nous sommes là pour vous répondre.
    </p>
@else
    <ol {{ $attributes->merge(['class' => 'relative grid grid-cols-4']) }}>
        {{-- Fil de fond, puis fil de progression jusqu'à l'étape en cours. --}}
        <div aria-hidden="true" class="absolute left-[12.5%] right-[12.5%] top-5 h-px bg-brand-ink/10"></div>
        <div aria-hidden="true" class="absolute left-[12.5%] top-5 h-px bg-brand-signature transition-all duration-700" style="width: {{ $currentIndex * 25 }}%"></div>

        @foreach ($steps as $index => $step)
            @php
                $done = $index <= $currentIndex;
                $isCurrent = $index === $currentIndex;
                $date = $reachedAt[$step['status']->value] ?? ($index === 0 ? $order->created_at : null);
            @endphp
            <li class="relative flex flex-col items-center text-center" @if ($isCurrent) aria-current="step" @endif>
                <span @class([
                    'relative z-10 flex h-10 w-10 items-center justify-center rounded-full border transition',
                    'border-brand-signature bg-brand-signature text-brand-surface' => $done,
                    'ring-4 ring-brand-signature/15' => $isCurrent,
                    'border-brand-ink/15 bg-brand-surface text-brand-ink/30' => ! $done,
                ])>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}" />
                    </svg>
                </span>
                <span @class([
                    'mt-3 text-[0.65rem] sm:text-xs uppercase tracking-[0.12em]',
                    'font-medium text-brand-ink' => $done,
                    'text-brand-muted' => ! $done,
                ])>{{ $step['label'] }}</span>
                @if ($done && $date)
                    <span class="mt-1 text-[0.65rem] sm:text-xs text-brand-muted">{{ $date->translatedFormat('j M') }}</span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
