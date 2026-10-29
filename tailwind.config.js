import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Polices libres (Bunny Fonts) au registre haute couture : une didone
                // pour les titres, une géométrique pour le texte et les labels espacés.
                // Basculer ici suffira si la cliente achète des licences un jour.
                sans: ['Jost', ...defaultTheme.fontFamily.sans],
                display: ['"Bodoni Moda"', ...defaultTheme.fontFamily.serif],
            },
            // Jost a un œil plus petit qu'Inter : à taille égale, le texte paraissait
            // trop menu. Les petites tailles gagnent 1 à 2 px ; les titres ne bougent pas.
            fontSize: {
                xs: ['0.8125rem', { lineHeight: '1.125rem' }],
                sm: ['1rem', { lineHeight: '1.5rem' }],
                base: ['1.125rem', { lineHeight: '1.875rem' }],
            },
            maxWidth: {
                // Largeur de la colonne de page, partagée par l'en-tête, le hero,
                // toutes les sections et le pied de page.
                //
                // Le `max-w-7xl` de Tailwind (1280 px) enfermait le site dans une
                // colonne étroite : sur un écran de 1920 px, la navigation et le
                // titre du hero démarraient à 354 px du bord, alors que la maquette
                // fait courir son cadre sur ~92 % de la largeur de la page.
                //
                // 1600 px conserve malgré tout un plafond : au-delà, les lignes de
                // texte s'allongent au point de gêner la lecture, que le rapport
                // veut « confortable » (jeton TEXTE, p.13).
                shell: '1600px',
            },
            // Palette « Atelier Nocturne » (rapport d'identité Aissatou Store V3).
            // Les jetons sont nommés par RÔLE et non par couleur : un futur
            // changement d'identité ne touchera que ce fichier, pas les vues.
            colors: {
                brand: {
                    ink: '#17151B',        // Texte et ancrage — 38 % de la surface
                    signature: '#4A1833',  // Cassis laqué, profondeur — 25 %
                    parchment: '#F4E6D5',  // Fond de section, respiration — 20 %
                    sage: '#A7AE91',       // Contrepoint textile — 10 %
                    accent: '#9F2D40',     // Rouge garance, signal et focus — 7 % MAX
                    // Deux nuances de service absentes du rapport, dérivées pour
                    // les besoins de l'interface :
                    surface: '#FBF7F1',    // Fond de page — le Parchemin pur en pleine
                                           // page écrase le contenu ; version éclaircie.
                    muted: '#6E6672',      // Texte secondaire — Encre désaturée, le
                                           // rapport ne fournit aucun gris.
                },
            },
        },
    },

    plugins: [forms],
};
