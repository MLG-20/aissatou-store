<?php

namespace App\Filament\Pages\Auth;

use App\Services\Admin\TwoFactor;
use App\Services\AdminAudit;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;

class TwoFactorChallenge extends SimplePage
{
    protected static string $view = 'filament.pages.auth.two-factor-challenge';

    /** @var array{code: string} */
    public array $data = ['code' => ''];

    public function mount(TwoFactor $twoFactor): void
    {
        $admin = Filament::auth()->user();

        if (! $twoFactor->isEnrolled($admin)) {
            $this->redirect(route('filament.admin.two-factor.setup'));
        } elseif ($twoFactor->hasPassed($admin)) {
            $this->redirect(Filament::getUrl());
        }
    }

    public function getTitle(): string
    {
        return 'Code de sécurité';
    }

    public function verify(TwoFactor $twoFactor): void
    {
        $admin = Filament::auth()->user();
        $code = trim($this->data['code']);

        if ($twoFactor->tooManyAttempts($admin)) {
            $this->addError('data.code', 'Trop d\'essais. Merci de réessayer dans '.$twoFactor->secondsBeforeRetry($admin).' secondes.');

            return;
        }

        $byRecoveryCode = false;

        if (! $twoFactor->verifyCode($admin, $code)) {
            $byRecoveryCode = $twoFactor->useRecoveryCode($admin, $code);

            if (! $byRecoveryCode) {
                $twoFactor->recordFailure($admin);
                $this->addError('data.code', 'Ce code n\'est pas valable. Saisissez celui affiché en ce moment dans l\'application.');

                return;
            }
        }

        $twoFactor->clearFailures($admin);
        $twoFactor->markPassed($admin);
        session()->regenerate();

        if ($byRecoveryCode) {
            AdminAudit::record('admin.two_factor_recovery_code_used', $admin);
        }

        $this->redirect(session()->pull('url.intended', Filament::getUrl()));
    }
}
