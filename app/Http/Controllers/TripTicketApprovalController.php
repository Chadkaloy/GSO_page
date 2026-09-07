<?php

namespace App\Http\Controllers;

use App\Models\TripTicketApprovalLog;
use App\Models\TripTicketRecord;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TripTicketApprovalController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Maps each decision action to the trip status it requires beforehand
     * ('from') and the trip status it results in ('to'). Completed requires
     * Approved first — a trip has to have actually been approved and
     * happened before it can be marked done.
     */
    private const TRANSITIONS = [
        'Approved'  => ['from' => 'Pending',  'to' => 'Approved'],
        'Rejected'  => ['from' => 'Pending',  'to' => 'Cancelled'],
        'Completed' => ['from' => 'Approved', 'to' => 'Completed'],
    ];

    public function index()
    {
        return inertia('TripTicketApproval/Index');
    }

    public function list(Request $request)
    {
        $query = TripTicketApprovalLog::with(['trip', 'approver']);

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['action', 'comments']);
        }

        if ($request->has('trip_id')) {
            $query->where('trip_id', $request->input('trip_id'));
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'trip_id'     => 'required|exists:trip_ticket_record,id',
            // 'Pending' isn't a decision, so it isn't a valid logged action —
            // Approved/Rejected/Completed are all real decisions someone makes.
            'action'      => 'required|string|max:20|in:Approved,Rejected,Completed',
            'comments'    => 'nullable|string',
            'action_date' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $trip = TripTicketRecord::findOrFail($validated['trip_id']);
            $transition = self::TRANSITIONS[$validated['action']];

            // The trip must currently be in the required prior state — this
            // is what prevents e.g. marking a still-Pending trip Completed,
            // or re-approving something already resolved.
            if ($trip->status !== $transition['from']) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        'trip_id' => [
                            "Trip {$trip->trip_no} is currently '{$trip->status}'. Marking it '{$validated['action']}' requires it to be '{$transition['from']}' first.",
                        ],
                    ],
                ], 422);
            }

            // The approver is always whoever is logged in taking the action —
            // never client-supplied. approver_id must be an
            // emp_accounts_record.accID, NOT a users.id, so resolve the
            // logged-in user's linked employee record rather than assuming
            // Auth::id() happens to match an accID.
            $employeeRecord = Auth::user()?->employeeRecord;
            if (!$employeeRecord) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        'trip_id' => ['Your account is not yet linked to an employee record. Ask an admin to assign you a role on the Employee Accounts page before approving trips.'],
                    ],
                ], 422);
            }
            $validated['approver_id'] = $employeeRecord->accID;

            $log = TripTicketApprovalLog::create($validated);

            // Sync structural status constraints back cleanly to the parent record
            $trip->update([
                'status'  => $transition['to'],
                'remarks' => $validated['action'] === 'Rejected' ? $validated['comments'] : $trip->remarks,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Approval status parsed and written out successfully.',
                'log'     => $log->load(['trip', 'approver'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Database storage trace rejection: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $log = TripTicketApprovalLog::with(['trip', 'approver'])->findOrFail($id);
        return response()->json($log);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $log = TripTicketApprovalLog::findOrFail($id);

        $validated = $request->validate([
            'trip_id'     => 'sometimes|exists:trip_ticket_record,id',
            'action'      => 'sometimes|string|max:20|in:Approved,Rejected,Completed',
            'comments'    => 'nullable|string',
            'action_date' => 'sometimes|date',
        ]);

        // approver_id is intentionally never editable here — the recorded
        // approver of a decision shouldn't change just because someone edits
        // the comments or date on it later.

        try {
            DB::beginTransaction();

            $log->update($validated);

            // Keep the parent trip's status in sync if the decision itself
            // changed. Editing doesn't re-check the 'from' precondition (the
            // log already exists and already moved the trip once), it just
            // re-points the trip at whatever status this action maps to.
            if (isset($validated['action'])) {
                $trip = TripTicketRecord::find($log->trip_id);
                if ($trip) {
                    $transition = self::TRANSITIONS[$validated['action']];
                    $trip->update([
                        'status'  => $transition['to'],
                        'remarks' => $validated['action'] === 'Rejected' ? ($validated['comments'] ?? $trip->remarks) : $trip->remarks,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'System decision index notes modified.',
                'log'     => $log->fresh()->load(['trip', 'approver'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update approval log: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $log = TripTicketApprovalLog::findOrFail($id);
        $log->delete();

        return response()->json(['message' => 'Log event safely cleared.'], 200);
    }
}