<?php

use App\Enums\UserPermission;
use App\Support\RolesAndPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Add the CRM permissions (view all records, see financials, manage pipelines) to databases that already have roles.
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
        Permission::query()->whereIn('name', [
            UserPermission::ViewAllRecords->value,
            UserPermission::SeeFinancials->value,
            UserPermission::ManagePipelines->value,
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
