<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * Shared login page for Atlas and Octa (register the same class on both
 * panels' ->login()) — one "email or mobile number" field resolves to a User
 * by either column, then password auth proceeds as normal.
 * canAccessPanel() (see User::canAccessPanel) is what actually keeps, say, a
 * vendor out of Atlas — this page doesn't need to know about roles at all.
 *
 * OTP login (mentioned as a future option): plug it in as a second tab/mode
 * on this same page — e.g. a "Login with OTP" toggle that swaps the password
 * field for a "send OTP" step reusing App\Services\OtpService (the same one
 * the customer app's phone+OTP login already uses), then calls
 * Filament::auth()->login($user) directly instead of ->attempt() with a
 * password. Not built now — this page is just the extension point.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Email or mobile number')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login = trim($data['login'] ?? '');

        $user = User::where('email', $login)->orWhere('phone', $login)->first();

        return [
            // No match: an id that can never exist, so Auth::attempt() simply
            // fails to find a row rather than needing a separate early return.
            'id' => $user?->id ?? '00000000-0000-0000-0000-000000000000',
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
