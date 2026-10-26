@props(['post', 'size' => 'medium', 'number' => null])

{{-- Couverture d'un article : sa photo, sinon celle d'un produit lié
     (Post::coverUrl) ; à défaut, couverture typographique — fond Encre plissé,
     numéro et titre en Fraunces — pour qu'aucune case ne reste vide. --}}
@php $cover = $post->coverUrl($size); @endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-brand-ink']) }}>
    @if ($cover)
        <img src="{{ $cover }}" alt="" loading="lazy" style="object-position: {{ $post->focalPosition() }}" class="absolute inset-0 h-full w-full object-cover transition duration-[1400ms] ease-out group-hover:scale-[1.05]">
    @else
        <x-shop.fold-backdrop :cut="false" />
        <div class="absolute inset-0 flex flex-col justify-end p-6 sm:p-8 text-brand-surface">
            @if ($number)
                <span class="font-display text-5xl text-brand-surface/25">N° {{ $number }}</span>
            @endif
            <span class="mt-2 font-display text-xl leading-snug text-brand-surface/80">{{ $post->title }}</span>
        </div>
    @endif
</div>
