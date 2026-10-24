<?php

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('members.edit'))->assertRedirect(route('login'));
});

test('non-admins cannot visit the members page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('members.edit'))->assertForbidden();
});

test('non-admins cannot use the members component', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.members')
        ->assertForbidden();
});

test('admins see only members of their own company', function () {
    $admin = User::factory()->admin()->create();
    $colleague = User::factory()->for($admin->company)->create();
    $outsider = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('members.edit'))
        ->assertOk()
        ->assertSee($colleague->email)
        ->assertDontSee($outsider->email);
});

test('admins can create a user in their company who is sent a verification email', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->company_id)->toBe($admin->company_id)
        ->and($user->getRoleNames()->all())->toBe([UserRole::User->value])
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('creating a user rejects an email that is already registered', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->set('name', 'Jane Doe')
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('createUser')
        ->assertHasErrors(['email' => 'unique']);
});

test('admins can invite a user by email', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->set('inviteEmail', 'invitee@example.com')
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = $admin->company->invitations()->sole();

    expect($invitation->email)->toBe('invitee@example.com');
    Notification::assertSentTo($invitation, InvitationNotification::class);
});

test('re-inviting an email resends the invitation and refreshes its sent date', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->for($admin->company)->create(['updated_at' => now()->subWeek()]);

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->set('inviteEmail', $invitation->email)
        ->call('invite')
        ->assertHasNoErrors();

    expect($admin->company->invitations()->sole()->updated_at->isToday())->toBeTrue();
    Notification::assertSentTo($invitation, InvitationNotification::class);
});

test('inviting an already registered email is rejected', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->set('inviteEmail', $existing->email)
        ->call('invite')
        ->assertHasErrors(['inviteEmail' => 'unique']);

    Notification::assertNothingSent();
});

test('admins can revoke an invitation', function () {
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->for($admin->company)->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->call('revokeInvitation', $invitation->id);

    $this->assertModelMissing($invitation);
});

test('admins cannot revoke an invitation of another company', function () {
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->call('revokeInvitation', $invitation->id)
        ->assertNotFound();

    $this->assertModelExists($invitation);
});

test('admins can promote a user and demote an admin', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->for($admin->company)->create();

    $component = Livewire::actingAs($admin)->test('pages::settings.members');

    $component->call('toggleRole', $member->id);
    expect($member->fresh()->getRoleNames()->all())->toBe([UserRole::Admin->value]);

    $component->call('toggleRole', $member->id);
    expect($member->fresh()->getRoleNames()->all())->toBe([UserRole::User->value]);
});

test('admins can deactivate and reactivate a member', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->for($admin->company)->create();

    $component = Livewire::actingAs($admin)->test('pages::settings.members');

    $component->call('toggleActive', $member->id);
    expect($member->fresh()->isActive())->toBeFalse();

    $component->call('toggleActive', $member->id);
    expect($member->fresh()->isActive())->toBeTrue();
});

test('admins can remove a member', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->for($admin->company)->create();

    $token = $member->createToken('member')->accessToken;

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->call('removeMember', $member->id);

    $this->assertModelMissing($member);
    $this->assertModelMissing($token);
});

test('admins cannot change or remove themselves or users of other companies', function (string $action, Closure $target) {
    $admin = User::factory()->admin()->create();
    $user = $target($admin);
    $rolesBefore = $user->getRoleNames()->all();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->call($action, $user->id)
        ->assertNotFound();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'deactivated_at' => null]);
    expect($user->fresh()->getRoleNames()->all())->toBe($rolesBefore);
})->with([
    'toggle role of self' => ['toggleRole', fn (User $admin): User => $admin],
    'deactivate self' => ['toggleActive', fn (User $admin): User => $admin],
    'remove self' => ['removeMember', fn (User $admin): User => $admin],
    'toggle role of outsider' => ['toggleRole', fn (): User => User::factory()->create()],
    'deactivate outsider' => ['toggleActive', fn (): User => User::factory()->create()],
    'remove outsider' => ['removeMember', fn (): User => User::factory()->create()],
]);

test('admins cannot create or invite users when simple users are disabled', function () {
    config(['app.simple_users_enabled' => false]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('members.edit'))
        ->assertOk()
        ->assertDontSee(__('Create user'))
        ->assertDontSee(__('Invite user'));

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('createUser')
        ->assertForbidden();

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->set('inviteEmail', 'jane@example.com')
        ->call('invite')
        ->assertForbidden();

    expect(User::where('email', 'jane@example.com')->exists())->toBeFalse()
        ->and(Invitation::count())->toBe(0);
});

test('admins cannot demote admins when simple users are disabled', function () {
    config(['app.simple_users_enabled' => false]);
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->for($admin->company)->create();

    $this->actingAs($admin)
        ->get(route('members.edit'))
        ->assertDontSee(__('Make user'));

    Livewire::actingAs($admin)
        ->test('pages::settings.members')
        ->call('toggleRole', $otherAdmin->id)
        ->assertForbidden();

    expect($otherAdmin->fresh()->isAdmin())->toBeTrue();
});
