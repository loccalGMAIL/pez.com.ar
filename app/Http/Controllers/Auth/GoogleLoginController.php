<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Panel login with Google. There is no sign-up: the Google account must have
 * the same email as a user already created (php artisan make:filament-user).
 */
class GoogleLoginController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        return $this->google()
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        try {
            $google = $this->google()->user();
        } catch (Throwable $e) {
            Log::warning('Google login failed', ['error' => $e->getMessage()]);

            return $this->backToLogin('No se pudo ingresar con Google. Probá de nuevo.');
        }

        $email = mb_strtolower((string) $google->getEmail());
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        $verified = $google instanceof GoogleUser && ($google->getRaw()['email_verified'] ?? false);

        if (! $user || ! $verified) {
            return $this->backToLogin("La cuenta {$email} no tiene acceso al sistema.");
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar_url' => $google->getAvatar(),
        ])->save();

        Filament::auth()->login($user, remember: true);
        session()->regenerate();

        return redirect()->intended(Filament::getUrl());
    }

    /**
     * The callback lives on the panel's own domain, so it's built per request.
     */
    private function google(): GoogleProvider
    {
        $provider = Socialite::driver('google');

        if (! $provider instanceof GoogleProvider) {
            throw new LogicException('The "google" Socialite driver must be the Google OAuth 2 provider.');
        }

        return $provider->redirectUrl(route('filament.admin.auth.google.callback'));
    }

    private function backToLogin(string $message): RedirectResponse
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->persistent()
            ->send();

        return redirect()->to(Filament::getLoginUrl());
    }
}
