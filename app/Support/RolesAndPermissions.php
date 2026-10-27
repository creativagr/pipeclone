<?php

namespace App\Support;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissions
{
    /**
     * Create every role and permission from the enums and give each role its permissions. Safe to run again:
     * a migration that adds a UserPermission case calls this to bring existing databases up to date.
     */
    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->syncPermissions(
                array_map(fn (UserPermission $permission): string => $permission->value, $role->permissions()),
            );
        }
    }
}
