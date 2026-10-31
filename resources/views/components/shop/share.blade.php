{{-- « Partager » : sur téléphone, le menu natif (WhatsApp, Instagram, TikTok,
     Messenger, SMS… au choix de la cliente) ; sinon WhatsApp ou copier le
     lien. L'aperçu (photo, prix) vient des balises og: de la page. --}}
@props(['url', 'text'])

<div
    x-data="{
        open: false,
        copied: false,
        share() {
            if (navigator.share) {
                navigator.share({ title: @js($text), text: @js($text), url: @js($url) }).catch(() => {});
                return;
            }
            this.open = ! this.open;
        },
        async copy() {
            try { await navigator.clipboard.writeText(@js($url)); this.copied = true; setTimeout(() => this.copied = false, 2000); } catch (e) {}
        },
    }"
    @click.outside="open = false"
    {{ $attributes->class('relative inline-block') }}
>
    <button type="button" @click="share()" class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.15em] text-brand-muted transition hover:text-brand-signature">
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
        </svg>
        Partager
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="absolute left-0 z-20 mt-2 w-56 rounded-sm border border-brand-ink/10 bg-white py-1 shadow-lg">
        <a href="https://wa.me/?text={{ rawurlencode($text.' '.$url) }}" target="_blank" rel="noopener" class="block px-4 py-2.5 text-sm text-brand-ink hover:bg-brand-surface">
            Sur WhatsApp
        </a>
        <button type="button" @click="copy()" class="block w-full px-4 py-2.5 text-left text-sm text-brand-ink hover:bg-brand-surface">
            <span x-text="copied ? 'Lien copié ✓' : 'Copier le lien'">Copier le lien</span>
        </button>
    </div>
</div>
