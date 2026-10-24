<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Create the roles and permissions, so every environment (tests included) has them without seeding.
     * This runs once per database: a new UserPermission case needs its own migration to reach existing databases.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::query()->whereIn('name', array_column(UserRole::cases(), 'value'))->delete();
        Permission::query()->whereIn('name', array_column(UserPermission::cases(), 'value'))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
