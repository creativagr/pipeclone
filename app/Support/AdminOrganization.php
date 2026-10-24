<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AdminOrganization
{
    /**
     * Authorize the current user as an organization admin and return their organization.
     */
    public static function resolve(): Organization
    {
        Gate::authorize('manage-organization');

        return Auth::user()->organization;
    }
}
