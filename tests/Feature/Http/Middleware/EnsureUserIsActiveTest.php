<?php

use App\Models\User;

test('deactivated users are logged out and sent to the login page', function () {
    $this->actingAs(User::factory()->deactivated()->create());

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your account has been deactivated.');

    $this->assertGuest();
});

test('deactivated users cannot use API tokens', function () {
    $token = User::factory()->deactivated()->create()->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
});
