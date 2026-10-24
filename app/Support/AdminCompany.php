<?php

namespace App\Support;

use App\Enums\UserPermission;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AdminCompany
{
    /**
     * Authorize the current user as a company admin and return their company.
     */
    public static function resolve(): Company
    {
        Gate::authorize(UserPermission::ManageCompany->value);

        return Auth::user()->company;
    }
}
