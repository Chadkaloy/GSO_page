<?php

namespace App\Http\Controllers;

use App\Models\TripTicketMaintenance;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TripTicketMaintenanceController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('TripTicketMaintenance/Index');
    }

    public function list(Request $request)
    {
        // Eager load the vehicle relationship to pull plate number data, and
        // the recorder relationship to show who logged the entry.
        $query = TripTicketMaintenance::with(['vehicle', 'recorder:accID,fullName']);

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['maintenance_type', 'description', 'service_provider']);
        }

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('maintenance_date', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'vehicle_id'            => 'required|exists:trip_ticket_vehicles,id',
            'maintenance_date'      => 'required|date',
            'maintenance_type'      => 'required|string|max:50',
            'description'           => 'required|string',
            'cost'                  => 'required|numeric|min:0',
            'service_provider'      => 'required|string|max:100',
            'status'                => 'required|string|max:20',
            'next_maintenance_date' => 'nullable|date',
            'remarks'               => 'nullable|string',
        ]);

        // recorded_by is always whoever is logged in entering the record —
        // never client-supplied/typed. Same pattern as Fuel Record's
        // recorded_by, Trip Ticket's created_by, and Approval's approver_id.
        $employeeRecord = Auth::user()?->employeeRecord;
        if (!$employeeRecord) {
            return response()->json([
                'errors' => [
                    'recorded_by' => ['Your account is not yet linked to an employee record. Ask an admin to assign you a role on the Employee Accounts page before logging maintenance records.'],
                ],
            ], 422);
        }
        $validated['recorded_by'] = $employeeRecord->accID;

        $maintenance = TripTicketMaintenance::create($validated);

        return response()->json([
            'message'     => 'Maintenance record logged successfully!',
            'maintenance' => $maintenance->load('recorder:accID,fullName')
        ], 201);
    }

    public function show($id)
    {
        // Eager load vehicle + recorder relations for show/edit operations
        $maintenance = TripTicketMaintenance::with(['vehicle', 'recorder:accID,fullName'])->findOrFail($id);
        return response()->json($maintenance);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $maintenance = TripTicketMaintenance::findOrFail($id);

        $validated = $request->validate([
            'vehicle_id'            => 'sometimes|exists:trip_ticket_vehicles,id',
            'maintenance_date'      => 'sometimes|date',
            'maintenance_type'      => 'sometimes|string|max:50',
            'description'           => 'sometimes|string',
            'cost'                  => 'sometimes|numeric|min:0',
            'service_provider'      => 'sometimes|string|max:100',
            'status'                => 'sometimes|string|max:20',
            'next_maintenance_date' => 'nullable|date',
            'remarks'               => 'nullable|string',
            // recorded_by intentionally not editable here — same reasoning
            // as approver_id on the Approval log and recorded_by on Fuel.
        ]);

        $maintenance->update($validated);

        return response()->json([
            'message'     => 'Maintenance record updated successfully!',
            'maintenance' => $maintenance->fresh()->load('recorder:accID,fullName')
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $maintenance = TripTicketMaintenance::findOrFail($id);
        $maintenance->delete();

        return response()->json(['message' => 'Maintenance record deleted successfully.'], 200);
    }
}