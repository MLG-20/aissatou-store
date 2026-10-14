@php $collectionImage = $collection->sizedUrl('large'); @endphp

<x-shop-layout :title="$collection->name.' — '.config('app.name')" :transparent-header="(bool) $collectionImage">
    <section
        class="relative w-full border-b border-brand-ink/10 overflow-hidden bg-cover bg-center flex items-center {{ $collectionImage ? 'bg-brand-ink min-h-[300px] sm:min-h-[380px]' : 'bg-gradient-to-br from-brand-parchment via-brand-surface to-brand-muted/20 py-14 sm:py-20' }}"
        @if ($collectionImage) style="background-image: url('{{ $collectionImage }}');" @endif
    >
        @if ($collectionImage)
            <div class="absolute inset-0 bg-brand-ink/55"></div>
        @endif

        <div class="relative w-full max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
            <p class="text-xs uppercase tracking-[0.25em] {{ $collectionImage ? 'text-brand-surface/70' : 'text-brand-signature' }}">Collection</p>
            <h1 class="font-display text-4xl sm:text-5xl mt-2 {{ $collectionImage ? 'text-brand-surface' : 'text-brand-ink' }}">{{ $collection->name }}</h1>

            @if ($collection->description)
                <p class="mt-3 max-w-xl {{ $collectionImage ? 'text-brand-surface/85' : 'text-brand-muted' }}">{{ $collection->description }}</p>
            @endif
        </div>
    </section>

    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8">
        <x-shop.filter-bar :action="route('shop.collection', $collection)" :materials="$materials">
            @include('shop.partials.product-grid', ['products' => $products])
        </x-shop.filter-bar>
    </div>
</x-shop-layout>
