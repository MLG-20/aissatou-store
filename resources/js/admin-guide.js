import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { GUIDES } from './admin-guides-data';

/*
 * Moteur des visites guidées du back-office : une bulle montre où cliquer, sur
 * le vrai écran. Lancées depuis la page Aide (?guide=…), elles continuent d'une
 * page à l'autre grâce au sessionStorage. Rien n'est saisi ni enregistré à la
 * place de la personne. Les étapes sont dans admin-guides-data.js.
 */
const STATE_KEY = 'admin-guide';

const readState = () => {
    try {
        return JSON.parse(sessionStorage.getItem(STATE_KEY));
    } catch {
        return null;
    }
};

const writeState = (state) => {
    try {
        sessionStorage.setItem(STATE_KEY, JSON.stringify(state));
    } catch {
        // Stockage bloqué : le guide s'arrêtera au changement de page.
    }
};

const clearState = () => {
    try {
        sessionStorage.removeItem(STATE_KEY);
    } catch {
        // Rien à effacer.
    }
};

// `*` remplace le numéro d'une fiche : catalogue/products/*/edit.
const isOn = (path) => new RegExp(`^/admin/?${path.replaceAll('*', '[^/]+')}/?$`).test(location.pathname);

const visible = (element) => {
    const box = element?.getBoundingClientRect();

    return box && box.width > 0 && box.height > 0 && box.right > 0 ? element : null;
};

const hrefOf = (element) => element?.href ?? element?.querySelector('a[href]')?.href;

function start() {
    const url = new URL(location.href);
    const requested = url.searchParams.get('guide');

    if (requested && GUIDES[requested]) {
        writeState({ guide: requested, index: 0, urls: {} });
        url.searchParams.delete('guide');
        history.replaceState(null, '', url);
    }

    const state = readState();
    const steps = state && GUIDES[state.guide];

    if (! steps?.[state.index] || ! isOn(steps[state.index].path)) {
        clearState();

        return;
    }

    // Sur téléphone, le menu ouvert cacherait ce qu'on veut montrer.
    if (window.innerWidth < 1024) {
        window.Alpine?.store('sidebar')?.close();
    }

    const here = steps.map((step, index) => ({ step, index })).filter(({ step }) => isOn(step.path));
    let leaving = false;

    const leaveTo = (index, destination) => {
        leaving = true;
        writeState({ ...state, index });
        location.href = destination;
    };

    const tour = driver({
        animate: ! matchMedia('(prefers-reduced-motion: reduce)').matches,
        allowClose: true,
        // Un clic à côté de la bulle ne doit pas arrêter le guide par accident.
        overlayClickBehavior: () => {},
        nextBtnText: 'Suivant',
        prevBtnText: 'Précédent',
        doneBtnText: 'Terminer',
        showProgress: true,
        steps: here.map(({ step, index }) => {
            // Cherché à chaque fois : certains blocs (chiffres du tableau de bord)
            // arrivent juste après la page.
            const element = () => visible(step.element?.());
            const next = steps[index + 1];
            const destination = () => (next?.path.includes('*') ? hrefOf(element()) : `/admin/${next?.path ?? ''}`);
            // Fiche à ouvrir alors que la liste est vide : le guide s'arrête là.
            const blocked = next && ! isOn(next.path) && ! destination();
            const last = ! next || blocked;

            return {
                element: () => element() ?? undefined,
                popover: {
                    title: step.title,
                    description: blocked ? (step.emptyText ?? step.text) : step.text,
                    progressText: `Étape ${index + 1} sur ${steps.length}`,
                    showButtons: ['next', 'previous', 'close'],
                    doneBtnText: last ? 'Terminer' : 'Suivant',
                    onNextClick: () => {
                        if (last) {
                            tour.destroy();
                        } else if (isOn(next.path)) {
                            tour.moveNext();
                        } else {
                            leaveTo(index + 1, destination());
                        }
                    },
                    onPrevClick: () => {
                        const previous = steps[index - 1];

                        if (previous && ! isOn(previous.path)) {
                            leaveTo(index - 1, state.urls[index - 1] ?? `/admin/${previous.path}`);
                        } else {
                            tour.movePrevious();
                        }
                    },
                },
                onHighlighted: (highlighted) => {
                    state.urls[index] = location.pathname;
                    writeState({ ...state, index });

                    // La personne clique vraiment sur le bouton montré : le lien
                    // l'emmène à l'étape suivante, ou le guide lui laisse la main
                    // pour remplir la fenêtre qui s'ouvre.
                    highlighted?.addEventListener('click', () => {
                        if (step.clickEnds) {
                            tour.destroy();
                        } else if (next && ! isOn(next.path)) {
                            leaving = true;
                            writeState({ ...state, index: index + 1 });
                        }
                    }, { once: true });
                },
            };
        }),
        onDestroyed: () => {
            if (! leaving) {
                clearState();
            }
        },
    });

    tour.drive(state.index - here[0].index);
}

window.addEventListener('load', () => setTimeout(start, 300));
