<?php

use App\Enums\UserType;
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

test('registering creates an organization named after the user with the user as admin', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->organization->name)->toBe('John Doe')
        ->and($user->type)->toBe(UserType::Admin);
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
