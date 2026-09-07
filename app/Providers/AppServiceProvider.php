<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Rules\Password::defaults(function(){
            return Rules\Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols();
        });

        /*
        |----------------------------------------------------------------
        | Global Action Gates
        |----------------------------------------------------------------
        | These check accLevel (S/A/E) on the user's linked
        | emp_accounts_record — NOT users.role. accLevel is a deliberately
        | separate field, kept apart from the login account's real role
        | (see EmpAccountsRecordController / Employee/Index.vue for the
        | Access Level dropdown that sets it).
        |
        | A user with no linked employee record has no accLevel to check,
        | so every gate below denies by default (in_array(null, [...])
        | is false) — matching RoleMiddleware's "block entirely" behavior
        | at the route level.
        |
        | Matrix, identical across all 21 modules for now:
        |   S (Super Admin) -> create, edit, delete, view, print
        |   A (Admin)       -> create, edit, view, print (no delete)
        |   E (Employee)    -> view, print only
        |
        | If a specific module ever needs an exception to this matrix,
        | define a module-specific gate (e.g. 'tripTicket.delete') and
        | check that one instead — don't overload these generic gates.
        */
        Gate::define('create', function ($user) {
            $accLevel = $user->employeeRecord?->accLevel;
            return in_array($accLevel, ['S', 'A']);
        });

        Gate::define('edit', function ($user) {
            $accLevel = $user->employeeRecord?->accLevel;
            return in_array($accLevel, ['S', 'A']);
        });

        Gate::define('delete', function ($user) {
            $accLevel = $user->employeeRecord?->accLevel;
            return $accLevel === 'S';
        });

        Gate::define('view', function ($user) {
            $accLevel = $user->employeeRecord?->accLevel;
            return in_array($accLevel, ['S', 'A', 'E']);
        });

        Gate::define('print', function ($user) {
            $accLevel = $user->employeeRecord?->accLevel;
            return in_array($accLevel, ['S', 'A', 'E']);
        });

        Inertia::share([
            'auth' => function () {
                return [
                    'user' => auth()->check() ? [
                        'id' => auth()->user()->id,
                        'name' => auth()->user()->name,
                        'role' => auth()->user()->role->value, // Pass the user's role
                    ] : null,
                ];
            },
            // Frontend permission flags derived from the gates above, so
            // Vue components can conditionally show/hide Create/Edit/
            // Delete/Print buttons without re-implementing the role
            // matrix in JS. Kept flat (not nested per-module) to match
            // the generic gates — update both together if this changes.
            'permissions' => function () {
                if (!auth()->check()) {
                    return [
                        'create' => false,
                        'edit' => false,
                        'delete' => false,
                        'view' => false,
                        'print' => false,
                    ];
                }

                return [
                    'create' => Gate::allows('create'),
                    'edit' => Gate::allows('edit'),
                    'delete' => Gate::allows('delete'),
                    'view' => Gate::allows('view'),
                    'print' => Gate::allows('print'),
                ];
            },
        ]);

    }
}