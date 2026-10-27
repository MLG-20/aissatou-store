<?php

namespace App\Support;

/**
 * Numéros de téléphone sénégalais, saisis librement par les clientes
 * (« 77 123 45 67 », « +221 77… », « 00221… »).
 *
 * Les numéros restent enregistrés tels que saisis ; ils sont mis au format
 * international E.164 (+221XXXXXXXXX) au moment de s'en servir, notamment
 * pour WhatsApp, qui n'accepte que ce format.
 */
final class PhoneNumber
{
    private const COUNTRY_CODE = '221';

    /**
     * +221 suivi des 9 chiffres nationaux, ou null si le numéro n'est pas un
     * numéro sénégalais valide (mobile en 7, fixe en 3).
     */
    public static function toE164(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        // Indicatif saisi en « 00221… » ou « +221… » / « 221… ».
        $digits = preg_replace('/^(00)?'.self::COUNTRY_CODE.'(?=\d{9}$)/', '', $digits);

        return preg_match('/^[37]\d{8}$/', $digits) ? '+'.self::COUNTRY_CODE.$digits : null;
    }

    /** Vrai pour un mobile (7X XXX XX XX) : seul un mobile a WhatsApp. */
    public static function isMobile(?string $number): bool
    {
        return str_starts_with((string) self::toE164($number), '+'.self::COUNTRY_CODE.'7');
    }
}
