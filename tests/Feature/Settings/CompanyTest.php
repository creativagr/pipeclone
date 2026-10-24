<?php

use App\Enums\UserPermission;
use App\Models\Invitation;
use App\Models\User;
use Livewire\Livewire;

test('only admins may manage the company', function (Closure $makeUser, bool $allowed) {
    expect($makeUser()->can(UserPermission::ManageCompany->value))->toBe($allowed);
})->with([
    'admin' => [fn (): User => User::factory()->admin()->create(), true],
    'user' => [fn (): User => User::factory()->create(), false],
]);

test('non-admins cannot visit the company settings page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('company.edit'))->assertForbidden();
});

test('non-admins cannot use the company settings component', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.company')
        ->assertForbidden();
});

test('admins see the company settings page with the current name', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('company.edit'))
        ->assertOk()
        ->assertSee($admin->company->name);
});

test('admins can rename the company', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->set('name', 'Acme Inc')
        ->call('updateName')
        ->assertHasNoErrors();

    expect($admin->company->fresh()->name)->toBe('Acme Inc');
});

test('the company name is required', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->set('name', '')
        ->call('updateName')
        ->assertHasErrors(['name' => 'required']);
});

test('admins can delete the company with all its users and invitations', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->for($admin->company)->create();
    $invitation = Invitation::factory()->for($admin->company)->create();
    $outsider = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->set('password', 'password')
        ->call('deleteCompany')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertModelMissing($admin->company);
    $this->assertModelMissing($admin);
    $this->assertModelMissing($member);
    $this->assertModelMissing($invitation);
    $this->assertModelExists($outsider);
    $this->assertGuest();
});

test('deleting the company deletes the api tokens and roles of its users', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->for($admin->company)->create();
    $memberToken = $member->createToken('member')->accessToken;
    $outsider = User::factory()->create();
    $outsiderToken = $outsider->createToken('outsider')->accessToken;

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->set('password', 'password')
        ->call('deleteCompany');

    $this->assertModelMissing($memberToken);
    $this->assertModelExists($outsiderToken);
    $this->assertDatabaseMissing('model_has_roles', ['model_id' => $member->id]);
    $this->assertDatabaseMissing('model_has_roles', ['model_id' => $admin->id]);
    $this->assertDatabaseHas('model_has_roles', ['model_id' => $outsider->id]);
});

test('deleting the company requires the correct password', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->set('password', 'wrong-password')
        ->call('deleteCompany')
        ->assertHasErrors(['password']);

    $this->assertModelExists($admin->company);
});
