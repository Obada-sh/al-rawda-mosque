<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Support\Attempt;
use App\Http\Requests\Auth\LoginRequest;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Sign-in with a username instead of an email address: the admin of a
 * mosque laptop that is never online has no use for an email.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getUsernameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('اسم المستخدم')
            ->markAsRequired()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('كلمة المرور')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->markAsRequired()
            ->autocomplete('current-password')
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $credentials = Attempt::form(fn (): array => LoginRequest::validateData($data), $this->form);

        return [
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
        ]);
    }
}
