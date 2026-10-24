<?php

use App\Models\User;
use Livewire\Livewire;

test('api tokens page is displayed', function () {
    $user = User::factory()->create();
    $user->createToken('CI server');

    $this->actingAs($user)
        ->get(route('api-tokens.edit'))
        ->assertOk()
        ->assertSee('CI server');
});

test('users can create an api token that authenticates api requests', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('tokenName', 'CI server')
        ->call('createToken')
        ->assertHasNoErrors();

    $plainTextToken = $component->get('plainTextToken');

    expect($user->tokens()->sole()->name)->toBe('CI server');

    auth()->forgetGuards();

    $this->withToken($plainTextToken)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('the api token name is required', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.api-tokens')
        ->call('createToken')
        ->assertHasErrors(['tokenName' => 'required']);
});

test('users can delete their api token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('CI server')->accessToken;

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->call('deleteToken', $token->id);

    $this->assertModelMissing($token);
});

test('users cannot delete an api token of another user', function () {
    $token = User::factory()->create()->createToken('CI server')->accessToken;

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.api-tokens')
        ->call('deleteToken', $token->id)
        ->assertNotFound();

    $this->assertModelExists($token);
});
