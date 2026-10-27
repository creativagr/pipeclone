<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;

class CurrentCompany
{
    private const string CONTEXT_KEY = 'company_id';

    /**
     * The company that company-scoped models are limited to: the one chosen with run(), otherwise the
     * signed-in user's company, otherwise none (and then scoped models see nothing).
     */
    public static function id(): ?int
    {
        $chosen = Context::getHidden(self::CONTEXT_KEY);

        if (is_int($chosen)) {
            return $chosen;
        }

        $user = Auth::user();

        return $user instanceof User ? $user->company_id : null;
    }

    /**
     * Run code for the given company where there is no signed-in user (commands, scheduled tasks, jobs).
     * The choice is kept in hidden context, so jobs dispatched inside the callback inherit it.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function run(Company $company, Closure $callback): mixed
    {
        $previous = Context::getHidden(self::CONTEXT_KEY);
        Context::addHidden(self::CONTEXT_KEY, $company->id);

        try {
            return $callback();
        } finally {
            $previous === null
                ? Context::forgetHidden(self::CONTEXT_KEY)
                : Context::addHidden(self::CONTEXT_KEY, $previous);
        }
    }
}
