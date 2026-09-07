<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Checks against accLevel (S/A/E on the linked emp_accounts_record),
     * NOT users.role. accLevel is kept as a deliberately separate field
     * from the login account's real role — see AppServiceProvider gates
     * for the same accLevel-based logic used for action-level permissions.
     *
     * Route usage: 'role:S,A,E' (letters, not the old super_admin/... names).
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $employeeRecord = auth()->user()->employeeRecord;

        // A user can be logged in (have a real users row) without yet being
        // linked to an emp_accounts_record. Since there's no accLevel to
        // check in that case, block entirely rather than silently falling
        // back to a default — an unlinked account should never pass as any
        // access level.
        if (!$employeeRecord) {
            abort(403, 'Your account is not yet linked to an employee record. Please contact your administrator to get access set up.');
        }

        $accLevel = $employeeRecord->accLevel;

        \Log::info('User accLevel:', ['accLevel' => $accLevel]);
        \Log::info('Required accLevels:', $roles);

        if (!in_array($accLevel, $roles)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}