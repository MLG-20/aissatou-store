<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'trust_items', 'marquee_items', 'about_story', 'about_quote', 'about_values',
    'contact_whatsapp', 'contact_email', 'contact_address', 'contact_hours',
    'footer_tagline', 'social_instagram', 'social_facebook', 'social_tiktok',
    'auth_title', 'auth_subtitle', 'auth_image_path',
    'low_stock_threshold',
])]
class SiteSetting extends Model
{
    /**
     * Miroir des défauts SQL : un modèle tout juste créé par current() ne
     * relit pas la base, il doit donc connaître lui-même ces valeurs.
     */
    protected $attributes = [
        'low_stock_threshold' => 3,
    ];

    protected function casts(): array
    {
        return [
            'trust_items' => 'array',
            'marquee_items' => 'array',
            'about_values' => 'array',
            'low_stock_threshold' => 'integer',
        ];
    }

    /**
     * Contenu éditable en singleton : une seule ligne existe toujours (id=1),
     * créée avec des valeurs par défaut au premier accès pour que le site
     * n'affiche jamais une section vide avant le premier réglage en admin.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'trust_items' => [
                'Payez à la réception, en toute confiance',
                'Une livraison préparée avec soin, rien que pour vous',
                'Une conseillère à votre écoute, comme une amie',
            ],
            'marquee_items' => [
                'Paiement à la réception',
                'Wave, Orange Money ou carte',
                'Livraison à Dakar et en régions',
                'Une conseillère sur WhatsApp',
                'Pièces choisies une à une',
            ],
            // Textes par défaut repris du positionnement officiel de la marque
            // (rapport d'identité p.2). Éditables depuis Filament.
            'about_story' => "Aissatou Store sélectionne des hijabs, foulards, cols, parfums et objets à offrir qui accompagnent la silhouette sans la surcharger. La valeur de la marque ne repose pas sur l'abondance, mais sur le discernement : moins d'objets, mieux choisis, mieux présentés.\n\nChaque pièce est pensée pour une femme attentive à la qualité, au tombé et à la cohérence — une élégance portable, faite d'objets choisis plutôt que d'une accumulation de références.",
            'about_quote' => 'Sélectionner, c\'est donner du sens.',
            'about_values' => [
                ['title' => 'Pudeur', 'text' => 'Une élégance mesurée, pensée pour accompagner plutôt que démontrer.'],
                ['title' => 'Choix', 'text' => 'La sélection devient un service et un signe de confiance.'],
                ['title' => 'Trace', 'text' => 'Chaque objet laisse une présence discrète et mémorable.'],
            ],
            'contact_whatsapp' => '+221 XX XXX XX XX',
            'contact_email' => 'contact@aissatoustore.test',
            'contact_address' => 'Dakar, Sénégal',
            'contact_hours' => 'Nous répondons du lundi au samedi. Les zones et délais de livraison vous sont précisés directement lors de la commande.',
            'footer_tagline' => "L'élégance voilée, pensée pour vous.",
            'social_instagram' => null,
            'social_facebook' => null,
            'social_tiktok' => null,
            'auth_title' => "L'élégance voilée, pensée pour vous.",
            'auth_subtitle' => 'Suivez vos commandes, gérez vos adresses et retrouvez votre univers en un instant.',
            'auth_image_path' => null,
        ]);
    }
}
