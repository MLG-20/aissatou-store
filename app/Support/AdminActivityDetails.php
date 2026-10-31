<?php

namespace App\Support;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Models\AdminActivity;

/**
 * Traduit une ligne du journal d'activité admin en français courant : une
 * phrase « En clair » et des détails lisibles (statuts, rôles et champs
 * modifiés en libellés, plus en noms de colonnes).
 */
class AdminActivityDetails
{
    /** Champs suivis par l'audit (comptes admin, codes promo, réglages). */
    private const FIELDS = [
        // Comptes admin
        'name' => 'nom',
        'email' => 'adresse e-mail',
        'is_active' => 'actif',
        // Codes promo
        'code' => 'code',
        'description' => 'note interne',
        'type' => 'type de réduction',
        'value' => 'montant de la réduction',
        'starts_at' => 'début de validité',
        'ends_at' => 'fin de validité',
        'min_subtotal' => 'minimum d\'achat',
        'max_uses' => 'nombre maximum d\'utilisations',
        'once_per_customer' => 'une seule utilisation par client',
        // Réglages du site
        'trust_items' => 'phrases de réassurance',
        'about_story' => 'histoire de la marque',
        'about_quote' => 'citation',
        'about_values' => 'valeurs',
        'contact_whatsapp' => 'WhatsApp',
        'contact_email' => 'e-mail de contact',
        'contact_address' => 'adresse',
        'contact_hours' => 'horaires / livraison',
        'footer_tagline' => 'accroche sous le logo',
        'social_instagram' => 'Instagram',
        'social_facebook' => 'Facebook',
        'social_tiktok' => 'TikTok',
        'auth_title' => 'titre de la page de connexion',
        'auth_subtitle' => 'sous-texte de la page de connexion',
        'auth_image_path' => 'image de la page de connexion',
        'low_stock_threshold' => 'seuil d\'alerte de stock faible',
    ];

    private const LABELS = [
        'de' => 'Ancien statut',
        'vers' => 'Nouveau statut',
        'avant' => 'Statut avant vérification',
        'apres' => 'Statut après vérification',
        'champs' => 'Champs modifiés',
        'role' => 'Rôle',
        'actif' => 'Compte actif',
        'mot_de_passe_change' => 'Mot de passe changé',
        'email' => 'Adresse saisie',
        'livreur' => 'Livreur / mode',
        'reduction' => 'Réduction',
    ];

    public static function sentence(AdminActivity $activity): string
    {
        $who = $activity->admin_name ?? 'Un admin inconnu';
        $subject = $activity->subject_label ?? '—';
        $details = $activity->details ?? [];

        return match ($activity->action) {
            'admin.login' => 'Connexion au back-office : '.$who.'.',
            'admin.login_failed' => 'Connexion refusée pour l\'adresse '.($details['email'] ?? 'inconnue').' (adresse ou mot de passe incorrect).',
            'admin.created' => $who.' a créé le compte admin '.$subject
                .(isset($details['role']) ? ' ('.AdminRoles::label($details['role']).')' : '').'.',
            'admin.updated' => $who.' a modifié le compte admin '.$subject.self::changes($details).'.',
            'order.status_changed' => $who.' a changé le statut de la commande '.$subject.' : '
                .self::orderStatus($details['de'] ?? null).' → '.self::orderStatus($details['vers'] ?? null).'.',
            'order.delivery_assigned' => $who.' a organisé la livraison de la commande '.$subject
                .(filled($details['livreur'] ?? null) ? ' ('.$details['livreur'].')' : '').'.',
            'courier.tour_link_sent' => $who.' a envoyé à '.$subject.' un nouveau lien « Ma tournée » (l\'ancien ne marche plus).',
            'courier.tour_link_revoked' => $who.' a révoqué le lien « Ma tournée » de '.$subject.'.',
            'payment.verified' => $who.' a vérifié auprès de PayTech le paiement de la commande '.$subject.' : '
                .(($details['avant'] ?? null) === ($details['apres'] ?? null)
                    ? 'statut inchangé ('.self::paymentStatus($details['apres'] ?? null).').'
                    : self::paymentStatus($details['avant'] ?? null).' → '.self::paymentStatus($details['apres'] ?? null).'.'),
            'settings.updated' => $who.' a modifié les réglages du site'.self::changes($details).'.',
            'legal_page.updated' => $who.' a modifié la page légale « '.$subject.' ».',
            'promo.created' => $who.' a créé le code promo '.$subject
                .(filled($details['reduction'] ?? null) ? ' ('.$details['reduction'].')' : '').'.',
            'promo.updated' => $who.' a modifié le code promo '.$subject.self::changes($details).'.',
            'customer.viewed' => $who.' a consulté la fiche du client '.$subject.'.',
            default => $who.' : '.$activity->actionLabel().' — '.$subject.'.',
        };
    }

    /** @return array<string, string> libellé => valeur */
    public static function of(AdminActivity $activity): array
    {
        $raw = $activity->details ?? [];
        $details = [];

        // Ordre de lecture fixe (« avant » puis « après »), la base triant
        // les clés JSON ; clés inconnues à la fin.
        $ordered = array_merge(array_intersect_key(array_flip(array_keys(self::LABELS)), $raw), $raw);

        foreach ($ordered as $key => $value) {
            $details[self::LABELS[$key] ?? $key] = self::value($activity->action, $key, $value);
        }

        return $details;
    }

    private static function value(string $action, string $key, mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' || $value === [] => '—',
            is_bool($value) => $value ? 'oui' : 'non',
            $key === 'champs' => self::fields((array) $value),
            $key === 'role' && is_array($value) => AdminRoles::label($value['de'] ?? null).' → '.AdminRoles::label($value['vers'] ?? null),
            $key === 'role' => AdminRoles::label((string) $value),
            in_array($key, ['de', 'vers'], true) && $action === 'order.status_changed' => self::orderStatus($value),
            in_array($key, ['avant', 'apres'], true) => self::paymentStatus($value),
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /** « : nom, adresse e-mail » (ou rien si aucun champ n'est listé). */
    private static function changes(array $details): string
    {
        $fields = (array) ($details['champs'] ?? []);

        if (isset($details['mot_de_passe_change'])) {
            $fields[] = 'password';
        }

        if (isset($details['role'])) {
            $fields[] = 'role';
        }

        return $fields ? ' : '.self::fields($fields) : '';
    }

    private static function fields(array $fields): string
    {
        return collect($fields)
            ->map(fn ($field) => match ($field) {
                'password' => 'mot de passe',
                'role' => 'rôle',
                default => self::FIELDS[$field] ?? (string) $field,
            })
            ->implode(', ');
    }

    private static function orderStatus(mixed $value): string
    {
        return OrderStatus::tryFrom((string) $value)?->label() ?? (string) ($value ?? '—');
    }

    private static function paymentStatus(mixed $value): string
    {
        return PaymentStatus::tryFrom((string) $value)?->label() ?? (string) ($value ?? '—');
    }
}
