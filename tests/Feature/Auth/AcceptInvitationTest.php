<?php

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

function acceptInvitationUrl(Invitation $invitation): string
{
    return URL::temporarySignedRoute('invitations.accept', now()->addDay(), ['invitation' => $invitation]);
}

test('a signed invitation link renders the accept page', function () {
    $invitation = Invitation::factory()->create();

    $this->get(acceptInvitationUrl($invitation))
        ->assertOk()
        ->assertSee($invitation->company->name);
});

test('an unsigned invitation link is rejected', function () {
    $invitation = Invitation::factory()->create();

    $this->get(route('invitations.accept', $invitation))->assertForbidden();
});

test('an expired invitation link is rejected', function () {
    $url = acceptInvitationUrl(Invitation::factory()->create());

    $this->travel(2)->days();

    $this->get($url)->assertForbidden();
});

test('a revoked invitation link returns 404', function () {
    $invitation = Invitation::factory()->create();
    $url = acceptInvitationUrl($invitation);
    $invitation->delete();

    $this->get($url)->assertNotFound();
});

test('accepting an invitation creates a verified user in the company and logs them in', function () {
    $invitation = Invitation::factory()->create();

    Livewire::test('pages::auth.accept-invitation', ['invitation' => $invitation])
        ->set('name', 'Jane Doe')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', $invitation->email)->sole();

    expect($user->company_id)->toBe($invitation->company_id)
        ->and($user->getRoleNames()->all())->toBe([UserRole::User->value])
        ->and($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
    $this->assertModelMissing($invitation);
});

test('accepting an invitation for an already registered email is rejected', function () {
    $invitation = Invitation::factory()->create();
    User::factory()->create(['email' => $invitation->email]);

    Livewire::test('pages::auth.accept-invitation', ['invitation' => $invitation])
        ->set('name', 'Jane Doe')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasErrors(['email' => 'unique']);

    $this->assertGuest();
    $this->assertModelExists($invitation);
});

test('invitations cannot be accepted when simple users are disabled', function () {
    config(['app.simple_users_enabled' => false]);

    $this->get(acceptInvitationUrl(Invitation::factory()->create()))->assertForbidden();
});
