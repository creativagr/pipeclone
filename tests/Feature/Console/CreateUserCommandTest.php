<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an admin with a new company', function () {
    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--new-company' => 'Acme',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->name)->toBe('Jane Doe')
        ->and($user->getRoleNames()->all())->toBe([UserRole::Admin->value])
        ->and($user->company->name)->toBe('Acme')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

test('it prompts for a new company', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Jane Doe')
        ->expectsQuestion('Email', 'jane@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->expectsQuestion('Company', '')
        ->expectsChoice('Company', 'new', ['new' => 'New company'])
        ->expectsQuestion('Company name', 'Acme')
        ->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->company->name)->toBe('Acme')
        ->and($user->getRoleNames()->all())->toBe([UserRole::Admin->value]);
});

test('it adds a user to an existing company', function (array $options, UserRole $role) {
    $company = Company::factory()->create();

    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--company' => $company->id,
        '--no-interaction' => true,
        ...$options,
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->company->is($company))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([$role->value])
        ->and(Company::count())->toBe(1);
})->with([
    'as user' => [[], UserRole::User],
    'as admin' => [['--admin' => true], UserRole::Admin],
]);

test('it searches for an existing company and prompts for the role', function () {
    $company = Company::factory()->create(['name' => 'Acme']);
    Company::factory()->create(['name' => 'Globex']);

    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Jane Doe')
        ->expectsQuestion('Email', 'jane@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->expectsQuestion('Company', 'acm')
        ->expectsChoice('Company', $company->id, ['new' => 'New company', $company->id => 'Acme'])
        ->expectsChoice('Role', 'admin', ['user' => 'User', 'admin' => 'Admin'])
        ->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->company->is($company))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([UserRole::Admin->value]);
});

test('it rejects invalid input without creating anything', function (array $options) {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--new-company' => 'Acme',
        '--no-interaction' => true,
        ...$options,
    ])->assertFailed();

    expect(User::count())->toBe(1)
        ->and(Company::count())->toBe(1);
})->with([
    'taken email' => [['--email' => 'taken@example.com']],
    'invalid email' => [['--email' => 'not-an-email']],
    'unknown company' => [['--company' => 999]],
    'empty company name' => [['--new-company' => '']],
    'too long company name' => [['--new-company' => str_repeat('a', 256)]],
]);

test('it rejects an existing and a new company together', function () {
    $company = Company::factory()->create();

    $this->artisan('user:create', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
        '--company' => $company->id,
        '--new-company' => 'Acme',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::count())->toBe(0)
        ->and(Company::count())->toBe(1);
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
