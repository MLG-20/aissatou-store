<?php

namespace App\Services\Admin;

use App\Models\Admin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Double authentification du back-office : code à 6 chiffres d'une application
 * (Google Authenticator…) ou code de secours à usage unique. Le calcul des codes
 * reste dans google2fa ; ici, le rejeu, les codes de secours et la session.
 */
class TwoFactor
{
    private const MAX_ATTEMPTS = 5;

    private const RECOVERY_CODES = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function isEnrolled(Admin $admin): bool
    {
        return $admin->two_factor_secret !== null && $admin->two_factor_confirmed_at !== null;
    }

    /** Le secret reste le même tant que la mise en place n'est pas finie : recharger la page ne casse pas le QR code déjà scanné. */
    public function start(Admin $admin): void
    {
        if ($admin->two_factor_secret === null) {
            $admin->forceFill(['two_factor_secret' => $this->google2fa->generateSecretKey(32)])->save();
        }
    }

    public function qrCodeSvg(Admin $admin): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $admin->email, $admin->two_factor_secret);

        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($url);
    }

    /**
     * @return array<int, string>|null Les codes de secours, affichés une seule fois ; null si le code est faux.
     */
    public function confirm(Admin $admin, string $code): ?array
    {
        if (! $this->verifyCode($admin, $code)) {
            return null;
        }

        $codes = collect(range(1, self::RECOVERY_CODES))
            ->map(fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();

        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map($this->hashRecoveryCode(...), $codes),
        ])->save();

        return $codes;
    }

    /** Un code accepté une fois ne l'est plus, même dans sa période de validité. */
    public function verifyCode(Admin $admin, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code) || $admin->two_factor_secret === null) {
            return false;
        }

        $result = $this->google2fa->verifyKeyNewer($admin->two_factor_secret, $code, $admin->two_factor_last_timestep);

        if ($result === false) {
            return false;
        }

        $timestep = $result === true ? $this->google2fa->getTimestamp() : $result;

        // Condition dans la requête : deux envois simultanés du même code ne passent pas tous les deux.
        $accepted = Admin::query()
            ->whereKey($admin->id)
            ->where(fn ($query) => $query->whereNull('two_factor_last_timestep')->orWhere('two_factor_last_timestep', '<', $timestep))
            ->update(['two_factor_last_timestep' => $timestep]) === 1;

        $admin->refresh();

        return $accepted;
    }

    public function useRecoveryCode(Admin $admin, string $code): bool
    {
        $hash = $this->hashRecoveryCode($code);

        $used = DB::transaction(function () use ($admin, $hash): bool {
            $fresh = Admin::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();
            $remaining = $fresh->two_factor_recovery_codes ?? [];

            if (! in_array($hash, $remaining, true)) {
                return false;
            }

            $fresh->forceFill(['two_factor_recovery_codes' => array_values(array_diff($remaining, [$hash]))])->save();

            return true;
        });

        $admin->refresh();

        return $used;
    }

    public function reset(Admin $admin): void
    {
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();
    }

    public function markPassed(Admin $admin): void
    {
        session()->put($this->sessionKey($admin), hash('sha256', (string) $admin->two_factor_secret));
    }

    /** Liée au secret : après une réinitialisation, une session déjà ouverte ne vaut plus. */
    public function hasPassed(Admin $admin): bool
    {
        return hash_equals((string) session()->get($this->sessionKey($admin)), hash('sha256', (string) $admin->two_factor_secret));
    }

    public function tooManyAttempts(Admin $admin): bool
    {
        return RateLimiter::tooManyAttempts($this->limiterKey($admin), self::MAX_ATTEMPTS);
    }

    public function secondsBeforeRetry(Admin $admin): int
    {
        return RateLimiter::availableIn($this->limiterKey($admin));
    }

    public function recordFailure(Admin $admin): void
    {
        RateLimiter::hit($this->limiterKey($admin), 60);
    }

    public function clearFailures(Admin $admin): void
    {
        RateLimiter::clear($this->limiterKey($admin));
    }

    private function hashRecoveryCode(string $code): string
    {
        return hash('sha256', Str::upper(trim($code)));
    }

    private function sessionKey(Admin $admin): string
    {
        return 'admin_two_factor_passed.'.$admin->id;
    }

    private function limiterKey(Admin $admin): string
    {
        return 'admin-two-factor:'.$admin->id;
    }
}
