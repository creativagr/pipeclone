<?php

use App\Enums\UserType;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('manage-organization gate allows only admins', function (UserType $type, bool $allowed) {
    $user = User::factory()->create(['type' => $type]);

    expect(Gate::forUser($user)->allows('manage-organization'))->toBe($allowed);
})->with([
    'admin' => [UserType::Admin, true],
    'user' => [UserType::User, false],
]);

test('non-admins cannot visit the organization settings page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('organization.edit'))->assertForbidden();
});

test('non-admins cannot use the organization settings component', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.organization')
        ->assertForbidden();
});

test('admins see the organization settings page with the current name', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('organization.edit'))
        ->assertOk()
        ->assertSee($admin->organization->name);
});

test('admins can rename the organization', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.organization')
        ->set('name', 'Acme Inc')
        ->call('updateName')
        ->assertHasNoErrors();

    expect($admin->organization->fresh()->name)->toBe('Acme Inc');
});

test('the organization name is required', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.organization')
        ->set('name', '')
        ->call('updateName')
        ->assertHasErrors(['name' => 'required']);
});

test('admins can delete the organization with all its users and invitations', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->for($admin->organization)->create();
    $invitation = Invitation::factory()->for($admin->organization)->create();
    $outsider = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.organization')
        ->set('password', 'password')
        ->call('deleteOrganization')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertModelMissing($admin->organization);
    $this->assertModelMissing($admin);
    $this->assertModelMissing($member);
    $this->assertModelMissing($invitation);
    $this->assertModelExists($outsider);
    $this->assertGuest();
});

test('deleting the organization deletes the api tokens of its users', function () {
    $admin = User::factory()->admin()->create();
    $memberToken = User::factory()->for($admin->organization)->create()->createToken('member')->accessToken;
    $outsiderToken = User::factory()->create()->createToken('outsider')->accessToken;

    Livewire::actingAs($admin)
        ->test('pages::settings.organization')
        ->set('password', 'password')
        ->call('deleteOrganization');

    $this->assertModelMissing($memberToken);
    $this->assertModelExists($outsiderToken);
});

test('deleting the organization requires the correct password', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.organization')
        ->set('password', 'wrong-password')
        ->call('deleteOrganization')
        ->assertHasErrors(['password']);

    $this->assertModelExists($admin->organization);
});
