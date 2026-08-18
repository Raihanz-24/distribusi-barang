<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    public function getMaxWidth(): Width|string|null
    {
        return Width::FiveExtraLarge;
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->placeholder('Masukkan username')
            ->prefixIcon('heroicon-o-user')
            ->autocomplete('username')
            ->autocapitalize('none')
            ->maxLength(50)
            ->required()
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Kata sandi')
            ->placeholder('Masukkan kata sandi')
            ->prefixIcon('heroicon-o-lock-closed');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Masuk ke Sistem')
            ->icon('heroicon-o-arrow-right-end-on-rectangle');
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => str($data['username'])->trim()->lower()->toString(),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => 'Username atau kata sandi tidak sesuai.',
        ]);
    }
}
