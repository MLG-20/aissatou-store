<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mise au format international des numéros sénégalais saisis librement :
 * WhatsApp n'accepte que +221XXXXXXXXX.
 */
class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function numbers(): array
    {
        return [
            'neuf chiffres' => ['771234567', '+221771234567'],
            'avec espaces' => ['77 123 45 67', '+221771234567'],
            'avec points et tirets' => ['77.123-45.67', '+221771234567'],
            'indicatif +221' => ['+221 77 123 45 67', '+221771234567'],
            'indicatif 00221' => ['00221771234567', '+221771234567'],
            'indicatif sans +' => ['221771234567', '+221771234567'],
            'fixe' => ['33 821 00 00', '+221338210000'],
            'huit chiffres' => ['77313166', null],
            'dix chiffres' => ['7712345678', null],
            'mauvais préfixe' => ['871234567', null],
            'autre pays' => ['+33 6 12 34 56 78', null],
            'vide' => ['', null],
            'absent' => [null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_are_normalized_to_e164(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($input));
    }

    public function test_only_mobiles_can_receive_whatsapp(): void
    {
        $this->assertTrue(PhoneNumber::isMobile('77 123 45 67'));
        $this->assertFalse(PhoneNumber::isMobile('33 821 00 00'));
        $this->assertFalse(PhoneNumber::isMobile('77313166'));
    }
}
