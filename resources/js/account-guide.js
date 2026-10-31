import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import '../css/account-guide.css';

/*
 * Petit guide de l'espace cliente : quelques bulles sur le tableau de bord,
 * lancées par la cliente depuis l'encart (jamais imposées). Chargé sur cette
 * page seulement.
 */
const visible = (element) => {
    const box = element?.getBoundingClientRect();

    return box && box.width > 0 && box.height > 0 ? element : null;
};

const mark = (name) => visible(document.querySelector(`[data-guide="${name}"]`));

// Sur téléphone, les rubriques sont rangées dans le menu déroulant : on montre ce menu.
const menuItem = (name) => () => mark(name) ?? mark('menu') ?? undefined;

const STEPS = [
    {
        title: 'Bienvenue chez vous',
        text: 'Voici votre espace personnel. En une minute, je vous montre où tout se trouve.',
    },
    {
        element: () => mark('commande') ?? undefined,
        title: 'Votre commande',
        text: 'Votre commande apparaît ici, et vous voyez où elle en est, étape par étape.',
    },
    {
        element: menuItem('account.orders.index'),
        title: 'Mes commandes',
        text: 'Dans le menu, « Mes commandes » regroupe toutes vos commandes et leur détail.',
    },
    {
        element: menuItem('shop.wishlist'),
        title: 'Mes coups de cœur',
        text: 'Touchez le cœur d\'une pièce qui vous plaît : vous la retrouverez ici, le temps d\'y réfléchir.',
    },
    {
        element: menuItem('account.addresses.index'),
        title: 'Mes adresses',
        text: 'Enregistrez où vous faire livrer. Si vous le souhaitez, ajoutez votre position exacte pour aider le livreur.',
    },
    {
        element: menuItem('profile.edit'),
        title: 'Mes informations',
        text: 'Pour changer votre nom, votre téléphone ou votre mot de passe.',
    },
    {
        element: () => mark('panier') ?? undefined,
        title: 'Mon panier',
        text: 'Les articles que vous avez choisis vous attendent ici, jusqu\'à ce que vous passiez commande.',
    },
    {
        title: 'C\'est tout !',
        text: 'Vous pouvez revoir ce guide quand vous voulez, en bas de cette page. Bonne visite.',
    },
];

document.querySelectorAll('[data-guide-start]').forEach((button) => {
    button.addEventListener('click', () => {
        driver({
            popoverClass: 'guide-boutique',
            animate: ! matchMedia('(prefers-reduced-motion: reduce)').matches,
            // Un toucher à côté de la bulle ne doit pas arrêter le guide par accident.
            overlayClickBehavior: () => {},
            overlayColor: '#17151B',
            stagePadding: 6,
            stageRadius: 0,
            showProgress: true,
            progressText: '{{current}} sur {{total}}',
            nextBtnText: 'Suivant',
            prevBtnText: 'Précédent',
            doneBtnText: 'Terminer',
            steps: STEPS.map((step) => ({
                element: step.element,
                popover: { title: step.title, description: step.text },
            })),
        }).drive();
    });
});
