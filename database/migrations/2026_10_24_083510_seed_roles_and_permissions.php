<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Support\RolesAndPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Create the roles and permissions, so every environment (tests included) has them without seeding.
     */
    public function up(): void
    {
        RolesAndPermissions::sync();
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
