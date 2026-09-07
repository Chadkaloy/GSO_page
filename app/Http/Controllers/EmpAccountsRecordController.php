<?php

namespace App\Http\Controllers;

use App\Models\EmpAccountsRecord;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmpAccountsRecordController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('Employee/Index');
    }

    public function list(Request $request)
    {
        $query = EmpAccountsRecord::with('user:id,name,email');

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['fullName', 'Pos']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('fullName', 'asc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            // Every employee/role record must be linked to a real login
            // account, and a given account can only hold one role record —
            // login credentials themselves are never collected or created
            // here, they already exist on the users table from registration.
            'user_id' => 'required|integer|exists:users,id|unique:emp_accounts_record,user_id',
            'accLevel' => 'required|string|max:1|in:S,A,E',
            'fullName' => 'required|string|max:25',
            'Age' => 'required|integer|min:18|max:100',
            'Gender' => 'required|string|max:25|in:Male,Female,Other',
            'Address' => 'required|string|max:150',
            'Pos' => 'required|string|max:25',
            'Mobile' => 'required|string|max:11|min:10',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('employees', 'public');
            $validated['image'] = Storage::url($path);
        }

        $employee = EmpAccountsRecord::create($validated);

        LocationController::remember($validated['Address'] ?? null);

        return response()->json([
            'message' => 'Employee created successfully!',
            'employee' => $employee->load('user:id,name,email')
        ], 201);
    }

    public function show($id)
    {
        $employee = EmpAccountsRecord::with('user:id,name,email')->findOrFail($id);
        return response()->json($employee);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $employee = EmpAccountsRecord::findOrFail($id);

        $this->abortIfTargetOutranksActor($employee);

        $validated = $request->validate([
            'user_id' => [
                'sometimes', 'integer', 'exists:users,id',
                Rule::unique('emp_accounts_record', 'user_id')->ignore($id, 'accID')
            ],
            'accLevel' => 'sometimes|string|max:1|in:S,A,E',
            'fullName' => 'sometimes|string|max:25',
            'Age' => 'sometimes|integer|min:18|max:100',
            'Gender' => 'sometimes|string|max:25|in:Male,Female,Other',
            'Address' => 'sometimes|string|max:150',
            'Pos' => 'sometimes|string|max:25',
            'Mobile' => 'sometimes|string|max:11|min:10',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($employee->image) {
                $oldPath = str_replace('/storage/', '', $employee->image);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('employees', 'public');
            $validated['image'] = Storage::url($path);
        }

        $employee->update($validated);

        if (isset($validated['Address'])) {
            LocationController::remember($validated['Address']);
        }

        return response()->json([
            'message' => 'Employee updated successfully!',
            'employee' => $employee->fresh()->load('user:id,name,email')
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $employee = EmpAccountsRecord::findOrFail($id);

        $this->abortIfTargetOutranksActor($employee);

        if ($employee->image) {
            $oldPath = str_replace('/storage/', '', $employee->image);
            Storage::disk('public')->delete($oldPath);
        }
        
        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully.'], 200);
    }

    /**
     * Lightweight lookup list for parent-selection dropdowns elsewhere in the
     * system (e.g. the Employee Accountability Card form) — every employee
     * record shown by accID + fullName. Distinct from availableUsers()
     * above, which lists *login* User accounts, not employee records.
     */
    public function lookupList()
    {
        return response()->json(
            EmpAccountsRecord::orderBy('fullName')->get(['accID', 'fullName'])
        );
    }

    /**
     * Lookup list for the "link to a User account" dropdown on the Employee
     * form — only users who don't already have an employee/role record
     * (a user can only be linked once). Edit mode should still show the
     * currently-linked user even though they're "taken"; the frontend
     * handles that the same way it does for other pending/available lists
     * (fetch-and-splice the current selection if it's missing).
     */
    public function availableUsers()
    {
        $users = \App\Models\User::whereDoesntHave('employeeRecord')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    /**
     * Fetches one specific user by id — used when editing an employee record
     * whose linked user no longer appears in availableUsers() (which makes
     * sense: they're linked to *this* record, so of course they're not
     * "available"). Lets the dropdown still show their name correctly.
     */
    public function showUser($id)
    {
        $user = \App\Models\User::findOrFail($id, ['id', 'name', 'email']);
        return response()->json($user);
    }

    /**
     * Prevents a lower-ranked account from editing or deleting a
     * higher-ranked one — e.g. an Admin (A) must not be able to edit or
     * delete a Super Admin's (S) employee record, including demoting them
     * via the Access Level field. Same-rank actions (Admin editing another
     * Admin, Super Admin editing another Super Admin) are still allowed,
     * as is editing one's own record.
     *
     * This is deliberately separate from the generic edit/delete Gates —
     * those answer "can this role perform this action at all", this
     * answers "can this specific actor act on this specific target".
     */
    private function abortIfTargetOutranksActor(EmpAccountsRecord $target): void
    {
        $rank = ['E' => 1, 'A' => 2, 'S' => 3];

        $actorLevel = Auth::user()?->employeeRecord?->accLevel;
        $actorRank = $rank[$actorLevel] ?? 0;
        $targetRank = $rank[$target->accLevel] ?? 0;

        if ($targetRank > $actorRank) {
            abort(403, 'You cannot modify an account with a higher access level than your own.');
        }
    }
}