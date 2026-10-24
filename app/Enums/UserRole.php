<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';

    /**
     * The permissions granted to this role.
     *
     * @return list<UserPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => UserPermission::cases(),
            self::User => [],
        };
    }
}
