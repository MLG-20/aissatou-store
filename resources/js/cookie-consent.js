// Bandeau de consentement (cf. App\Support\CookieConsent). Le choix est
// écrit dans un cookie lu par le serveur ; les scripts soumis à accord
// attendent dans des <template data-consent="…"> (composant <x-consent>)
// et sont activés sans recharger la page dès que le visiteur accepte.
const COOKIE = 'aissatou_consent';
const LIFETIME = 182 * 24 * 60 * 60;

function activate(category) {
    document.querySelectorAll(`template[data-consent="${category}"]`).forEach((template) => {
        const fragment = template.content.cloneNode(true);
        // Un <script> cloné depuis un <template> ne s'exécute pas : on le recrée.
        fragment.querySelectorAll('script').forEach((inert) => {
            const script = document.createElement('script');
            [...inert.attributes].forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
            script.text = inert.text;
            inert.replaceWith(script);
        });
        template.replaceWith(fragment);
    });
}

export default ({ categories, granted, open }) => ({
    open,
    customizing: false,
    granted,
    choices: Object.fromEntries(categories.map((category) => [category, granted.includes(category)])),

    init() {
        window.addEventListener('open-cookie-settings', () => {
            this.open = true;
            this.customizing = true;
        });
    },

    acceptAll() {
        this.save(categories);
    },

    rejectAll() {
        this.save([]);
    },

    saveChoices() {
        this.save(categories.filter((category) => this.choices[category]));
    },

    save(list) {
        const value = encodeURIComponent(JSON.stringify({ granted: list, asked: categories }));
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${COOKIE}=${value}; Max-Age=${LIFETIME}; Path=/; SameSite=Lax${secure}`;

        this.open = false;
        this.customizing = false;

        // Un script déjà chargé ne se décharge pas : un retrait d'accord
        // repart d'une page propre.
        if (this.granted.some((category) => !list.includes(category))) {
            location.reload();

            return;
        }

        list.filter((category) => !this.granted.includes(category)).forEach(activate);
        this.granted = list;
    },
});
