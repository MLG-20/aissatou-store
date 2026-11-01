<?php

namespace App\Filament\Pages\Auth;

use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Le mot de passe actuel est demandé pour changer le mot de passe ou l'e-mail :
 * une session restée ouverte ne suffit pas à prendre le contrôle du compte.
 */
class EditProfile extends BaseEditProfile
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent()->live(onBlur: true),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! static::isSimple()),
            ),
        ];
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label('Mot de passe actuel')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                $key = 'admin-current-password:'.$this->getUser()->getAuthIdentifier();

                if (RateLimiter::tooManyAttempts($key, 5)) {
                    $fail('Trop d\'essais. Merci de réessayer dans '.RateLimiter::availableIn($key).' secondes.');
                } elseif (! Hash::check((string) $value, $this->getUser()->password)) {
                    RateLimiter::hit($key, 60);
                    $fail('Le mot de passe est incorrect.');
                } else {
                    RateLimiter::clear($key);
                }
            })
            ->visible(fn (Get $get): bool => filled($get('password')) || $get('email') !== $this->getUser()->email)
            ->dehydrated(false);
    }
}
