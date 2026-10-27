<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleLoginController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Sign in an existing, active user whose verified Google address matches their email. Nobody is created here.
     * A user with two-factor authentication goes through the same two-factor challenge as a password login.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Exception) {
            return $this->refuse(__('Google sign-in was cancelled or expired. Please try again.'));
        }

        $isVerified = $googleUser instanceof AbstractUser && ($googleUser->getRaw()['email_verified'] ?? false) === true;
        $user = $isVerified ? User::query()->where('email', $googleUser->getEmail())->first() : null;

        if ($user === null || ! $user->isActive()) {
            return $this->refuse(__('No active account uses this Google address.'));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
