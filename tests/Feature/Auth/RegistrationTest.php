<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registering creates a company named after the user with the user as admin', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->company->name)->toBe('John Doe')
        ->and($user->getRoleNames()->all())->toBe([UserRole::Admin->value]);
});

test('registration can be disabled with an environment variable', function () {
    putenv('REGISTRATION_ENABLED=false');

    try {
        $this->refreshApplication();

        $this->get('/register')->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee(__('Sign up'));
    } finally {
        putenv('REGISTRATION_ENABLED');
    }
});
