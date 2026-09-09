<?php

namespace App\Http\Controllers;

use App\Models\TripTicketFuelRecord;
use App\Services\FuelAllocationService;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TripTicketFuelRecordController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('TripTicketFuel/Index');
    }

    public function list(Request $request)
    {
        $query = TripTicketFuelRecord::with(['trip', 'recorder:accID,fullName']);

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['or_number', 'station_name', 'remarks']);
        }

        if ($request->has('trip_id')) {
            $query->where('trip_id', $request->input('trip_id'));
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('fuel_date', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'trip_id'        => 'required|exists:trip_ticket_record,id',
            'fuel_liters'    => 'required|numeric|min:0.01',
            'fuel_cost'      => 'required|numeric|min:0.01',
            'odometer_start' => 'nullable|numeric|min:0',
            'odometer_end'   => 'nullable|numeric|min:0',
            'fuel_date'      => 'required|date',
            'station_name'   => 'required|string|max:100',
            'or_number'      => 'nullable|string|max:50',
            'remarks'        => 'nullable|string',
        ]);

        // recorded_by is always whoever is logged in entering the fuel log —
        // never client-supplied/typed. Resolved via the users<->emp_accounts_record
        // link, same pattern as created_by (Trip Ticket) and approver_id (Approval).
        $employeeRecord = Auth::user()?->employeeRecord;
        if (!$employeeRecord) {
            return response()->json([
                'errors' => [
                    'recorded_by' => ['Your account is not yet linked to an employee record. Ask an admin to assign you a role on the Employee Accounts page before logging fuel records.'],
                ],
            ], 422);
        }
        $validated['recorded_by'] = $employeeRecord->accID;

        $fuelRecord = TripTicketFuelRecord::create($validated);

        // Reconcile this trip's original fuel estimate (reserved at approval
        // time) against what was actually used — refunds the unused portion
        // back to the office's allocation, or deducts the extra if usage
        // exceeded the estimate. Silently does nothing if there's nothing to
        // reconcile against (trip had no estimate, or was already
        // reconciled by an earlier fuel log for the same trip) — never
        // blocks saving the fuel record itself.
        app(FuelAllocationService::class)->reconcileForTrip(
            $validated['trip_id'],
            (float) $validated['fuel_liters']
        );

        return response()->json([
            'message'     => 'Fuel purchase ledger entry added successfully.',
            'fuel_record' => $fuelRecord->load('recorder:accID,fullName')
        ], 201);
    }

    public function show($id)
    {
        // Eager load trip + recorder relationships to pull trip number and recorder name
        $fuelRecord = TripTicketFuelRecord::with(['trip', 'recorder:accID,fullName'])->findOrFail($id);
        return response()->json($fuelRecord);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $fuelRecord = TripTicketFuelRecord::findOrFail($id);

        $validated = $request->validate([
            'trip_id'        => 'sometimes|exists:trip_ticket_record,id',
            'fuel_liters'    => 'sometimes|numeric|min:0.01',
            'fuel_cost'      => 'sometimes|numeric|min:0.01',
            'odometer_start' => 'nullable|numeric|min:0',
            'odometer_end'   => 'nullable|numeric|min:0',
            'fuel_date'      => 'sometimes|date',
            'station_name'   => 'sometimes|string|max:100',
            'or_number'      => 'nullable|string|max:50',
            // recorded_by intentionally not editable here — same reasoning
            // as approver_id on the Approval log.
            'remarks'        => 'nullable|string',
        ]);

        // NOTE: editing fuel_liters here does NOT re-run reconciliation —
        // reconcileForTrip() already guards against double-refunding, and a
        // correction to a fuel log's liters after the fact is rare enough
        // that re-triggering allocation math here would add more risk
        // (silently double-adjusting) than it's worth for now.
        $fuelRecord->update($validated);

        return response()->json([
            'message'     => 'Fuel purchase ledger adjustments committed successfully.',
            'fuel_record' => $fuelRecord->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $fuelRecord = TripTicketFuelRecord::findOrFail($id);
        $fuelRecord->delete();

        return response()->json(['message' => 'Fuel transaction trace safely dropped.'], 200);
    }
}