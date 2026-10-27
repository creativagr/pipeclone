<?php

use App\Enums\UserPermission;
use App\Models\User;

test('admins have every permission', function (UserPermission $permission) {
    expect(User::factory()->admin()->create()->can($permission->value))->toBeTrue();
})->with(UserPermission::cases());

test('users have no permission by default', function (UserPermission $permission) {
    expect(User::factory()->create()->can($permission->value))->toBeFalse();
})->with(UserPermission::cases());
