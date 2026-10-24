<?php

use App\Enums\UserType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an admin with a new organization', function () {
    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--new-organization' => 'Acme',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->name)->toBe('Jane Doe')
        ->and($user->type)->toBe(UserType::Admin)
        ->and($user->organization->name)->toBe('Acme')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

test('it prompts for a new organization', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Jane Doe')
        ->expectsQuestion('Email', 'jane@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->expectsQuestion('Organization', '')
        ->expectsChoice('Organization', 'new', ['new' => 'New organization'])
        ->expectsQuestion('Organization name', 'Acme')
        ->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->organization->name)->toBe('Acme')
        ->and($user->type)->toBe(UserType::Admin);
});

test('it adds a user to an existing organization', function (array $options, UserType $type) {
    $organization = Organization::factory()->create();

    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--organization' => $organization->id,
        '--no-interaction' => true,
        ...$options,
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->organization->is($organization))->toBeTrue()
        ->and($user->type)->toBe($type)
        ->and(Organization::count())->toBe(1);
})->with([
    'as user' => [[], UserType::User],
    'as admin' => [['--admin' => true], UserType::Admin],
]);

test('it searches for an existing organization and prompts for the type', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    Organization::factory()->create(['name' => 'Globex']);

    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Jane Doe')
        ->expectsQuestion('Email', 'jane@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->expectsQuestion('Organization', 'acm')
        ->expectsChoice('Organization', $organization->id, ['new' => 'New organization', $organization->id => 'Acme'])
        ->expectsChoice('Type', 'admin', ['user' => 'User', 'admin' => 'Admin'])
        ->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->organization->is($organization))->toBeTrue()
        ->and($user->type)->toBe(UserType::Admin);
});

test('it rejects invalid input without creating anything', function (array $options) {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--new-organization' => 'Acme',
        '--no-interaction' => true,
        ...$options,
    ])->assertFailed();

    expect(User::count())->toBe(1)
        ->and(Organization::count())->toBe(1);
})->with([
    'taken email' => [['--email' => 'taken@example.com']],
    'invalid email' => [['--email' => 'not-an-email']],
    'unknown organization' => [['--organization' => 999]],
    'empty organization name' => [['--new-organization' => '']],
    'too long organization name' => [['--new-organization' => str_repeat('a', 256)]],
]);

test('it rejects an existing and a new organization together', function () {
    $organization = Organization::factory()->create();

    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--organization' => $organization->id,
        '--new-organization' => 'Acme',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::count())->toBe(0)
        ->and(Organization::count())->toBe(1);
});

test('it validates prompted input before moving on', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Jane Doe')
        ->expectsQuestion('Email', 'taken@example.com')
        ->expectsOutputToContain(__('validation.unique', ['attribute' => 'email']))
        ->assertFailed();

    expect(User::count())->toBe(1);
});
