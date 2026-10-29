<?php

namespace App\Models\Content;

use App\Enums\Content\LegalDocument;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['title', 'content', 'is_published'])]
class LegalPage extends Model
{
    /** Liens du pied de page, en cache (tableau simple, cf. AppServiceProvider). */
    public const FOOTER_CACHE_KEY = 'shop.legal_links';

    protected function casts(): array
    {
        return [
            'document' => LegalDocument::class,
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::FOOTER_CACHE_KEY));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public static function for(LegalDocument $document): ?self
    {
        return static::query()->where('document', $document)->first();
    }

    /**
     * Repères à écrire dans le texte, remplacés à l'affichage par l'identité
     * légale des réglages du site : la changer là-bas suffit, aucune page à
     * reprendre à la main.
     *
     * @return array<string, string> repère => libellé du réglage
     */
    public static function placeholders(): array
    {
        return [
            '{raison_sociale}' => 'Raison sociale',
            '{forme_juridique}' => 'Forme juridique',
            '{ninea}' => 'NINEA',
            '{rccm}' => 'RCCM',
            '{adresse_siege}' => 'Adresse du siège',
            '{responsable_publication}' => 'Responsable de la publication',
            '{hebergeur}' => 'Hébergeur',
            '{email}' => 'Email de contact',
            '{whatsapp}' => 'WhatsApp',
        ];
    }

    /**
     * Texte assaini (cf. RichText), repères remplacés. Une valeur manquante
     * s'affiche « [NINEA à compléter] » : visible à la relecture, jamais un
     * trou silencieux dans une page légale.
     */
    public function renderedContent(): string
    {
        $settings = SiteSetting::current();
        $values = [
            '{raison_sociale}' => $settings->legal_name,
            '{forme_juridique}' => $settings->legal_form,
            '{ninea}' => $settings->legal_ninea,
            '{rccm}' => $settings->legal_rccm,
            '{adresse_siege}' => $settings->legal_address,
            '{responsable_publication}' => $settings->legal_publisher,
            '{hebergeur}' => $settings->legal_host,
            '{email}' => $settings->contact_email,
            '{whatsapp}' => $settings->contact_whatsapp,
        ];

        $replacements = [];
        foreach (self::placeholders() as $placeholder => $label) {
            $replacements[$placeholder] = e(filled($values[$placeholder]) ? $values[$placeholder] : "[{$label} à compléter]");
        }

        return strtr(RichText::sanitize($this->content), $replacements);
    }
}
