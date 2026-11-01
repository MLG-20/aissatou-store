<?php

namespace App\Filament\Pages\Auth;

use App\Services\Admin\TwoFactor;
use App\Services\AdminAudit;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;

/**
 * Mise en place obligatoire à la première connexion. Les codes de secours ne
 * s'affichent qu'ici, une seule fois.
 */
class TwoFactorSetup extends SimplePage
{
    protected static string $view = 'filament.pages.auth.two-factor-setup';

    /** @var array{code: string} */
    public array $data = ['code' => ''];

    /** @var array<int, string>|null */
    public ?array $recoveryCodes = null;

    public function mount(TwoFactor $twoFactor): void
    {
        $admin = Filament::auth()->user();

        if ($twoFactor->isEnrolled($admin)) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $twoFactor->start($admin);
    }

    public function getTitle(): string
    {
        return 'Protégez votre compte';
    }

    public function confirm(TwoFactor $twoFactor): void
    {
        $admin = Filament::auth()->user();

        if ($twoFactor->tooManyAttempts($admin)) {
            $this->addError('data.code', 'Trop d\'essais. Merci de réessayer dans '.$twoFactor->secondsBeforeRetry($admin).' secondes.');

            return;
        }

        $codes = $twoFactor->confirm($admin, trim($this->data['code']));

        if ($codes === null) {
            $twoFactor->recordFailure($admin);
            $this->addError('data.code', 'Ce code n\'est pas le bon. Saisissez celui affiché en ce moment dans l\'application.');

            return;
        }

        $twoFactor->clearFailures($admin);
        $twoFactor->markPassed($admin);
        session()->regenerate();
        AdminAudit::record('admin.two_factor_enabled', $admin);

        $this->recoveryCodes = $codes;
    }

    public function finish(): void
    {
        $this->redirect(session()->pull('url.intended', Filament::getUrl()));
    }

    protected function getViewData(): array
    {
        $admin = Filament::auth()->user();

        return $this->recoveryCodes !== null ? [] : [
            'qrCode' => app(TwoFactor::class)->qrCodeSvg($admin),
            'secret' => $admin->two_factor_secret,
        ];
    }
}
