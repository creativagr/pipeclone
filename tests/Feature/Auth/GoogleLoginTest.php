<?php

use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

function fakeGoogleUser(string $email, bool $emailVerified = true): void
{
    Socialite::fake('google', GoogleUser::fake(['email' => $email, 'email_verified' => $emailVerified]));
}

test('the google button is shown on the login page only when google is configured', function (?string $clientId, ?string $clientSecret, bool $shown) {
    config(['services.google.client_id' => $clientId, 'services.google.client_secret' => $clientSecret]);

    $response = $this->get(route('login'));

    $shown
        ? $response->assertSee(route('auth.google.redirect'))
        : $response->assertDontSee(route('auth.google.redirect'));
})->with([
    'configured' => ['client-id', 'client-secret', true],
    'secret missing' => ['client-id', null, false],
    'not configured' => [null, null, false],
]);

test('users are redirected to google', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))->assertRedirect();
});

test('an existing active user signs in with their verified google address', function () {
    $user = User::factory()->unverified()->create(['email' => 'jane@example.com']);
    fakeGoogleUser('jane@example.com');

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('users with two-factor authentication still pass the two-factor challenge after google', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'jane@example.com']);
    fakeGoogleUser('jane@example.com');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

test('google sign-in is refused', function (Closure $arrange) {
    $arrange();

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::count())->toBeLessThanOrEqual(1);
})->with([
    'unknown address' => [fn () => fakeGoogleUser('nobody@example.com')],
    'deactivated user' => [function () {
        User::factory()->deactivated()->create(['email' => 'jane@example.com']);
        fakeGoogleUser('jane@example.com');
    }],
    'unverified google address' => [function () {
        User::factory()->create(['email' => 'jane@example.com']);
        fakeGoogleUser('jane@example.com', emailVerified: false);
    }],
    'denied or expired google response' => [fn () => Socialite::fake('google', fn () => throw new InvalidStateException)],
]);
