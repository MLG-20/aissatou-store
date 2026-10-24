/**
 * Apparition douce au défilement : tout élément `[data-reveal]` reçoit
 * `is-revealed` en entrant à l'écran (une seule fois). Le masquage initial
 * n'est appliqué en CSS que sous `.js-reveal` (posée ici) : sans JavaScript,
 * rien n'est caché. `--reveal-delay` règle une cascade éventuelle.
 */
export default function initReveal() {
    const elements = document.querySelectorAll('[data-reveal]');

    if (!elements.length || !('IntersectionObserver' in window)) {
        return;
    }

    document.documentElement.classList.add('js-reveal');

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
    );

    elements.forEach((element) => observer.observe(element));
}
