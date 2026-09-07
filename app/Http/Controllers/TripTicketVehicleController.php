<?php
// app/Http/Controllers/TripTicketVehicleController.php

namespace App\Http\Controllers;

use App\Models\TripTicketVehicle;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TripTicketVehicleController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('TripTicketVehicle/Index');
    }

    public function list(Request $request)
    {
        $query = TripTicketVehicle::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['plate_no', 'brand', 'model']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('plate_no', 'asc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'plate_no' => 'required|string|max:20|unique:trip_ticket_vehicles,plate_no',
            'vehicle_type' => 'required|string|max:50',
            'brand' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'year_model' => 'nullable|integer|min:1900|max:' . date('Y'),
            'color' => 'required|string|max:30',
            'engine_no' => 'nullable|string|max:50',
            'chassis_no' => 'nullable|string|max:50',
            'capacity' => 'required|integer|min:1',
            'status' => 'required|string|max:20|in:Available,In Use,Maintenance,Reserved',
            'remarks' => 'nullable|string',
        ]);

        $vehicle = TripTicketVehicle::create($validated);

        return response()->json([
            'message' => 'Vehicle created successfully!',
            'vehicle' => $vehicle
        ], 201);
    }

    public function show($id)
    {
        $vehicle = TripTicketVehicle::findOrFail($id);
        return response()->json($vehicle);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $vehicle = TripTicketVehicle::findOrFail($id);

        $validated = $request->validate([
            'plate_no' => 'sometimes|string|max:20|unique:trip_ticket_vehicles,plate_no,' . $id,
            'vehicle_type' => 'sometimes|string|max:50',
            'brand' => 'sometimes|string|max:50',
            'model' => 'sometimes|string|max:50',
            'year_model' => 'nullable|integer|min:1900|max:' . date('Y'),
            'color' => 'sometimes|string|max:30',
            'engine_no' => 'nullable|string|max:50',
            'chassis_no' => 'nullable|string|max:50',
            'capacity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|max:20|in:Available,In Use,Maintenance,Reserved',
            'remarks' => 'nullable|string',
        ]);

        $vehicle->update($validated);

        return response()->json([
            'message' => 'Vehicle updated successfully!',
            'vehicle' => $vehicle->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $vehicle = TripTicketVehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully.'], 200);
    }

    // Context helper for drop-downs when planning new trips
    public function getAvailableVehicles()
    {
        $vehicles = TripTicketVehicle::where('status', 'Available')
            ->orderBy('plate_no', 'asc')
            ->get(['id', 'plate_no', 'brand', 'model', 'capacity']);
            
        return response()->json($vehicles);
    }

    /**
     * Unfiltered lookup for dropdowns that need every vehicle regardless of
     * current status — e.g. the Maintenance form, since you can (and often
     * specifically do) log maintenance against a vehicle that's currently
     * "In Use" or already "Maintenance", not just "Available" ones.
     */
    public function lookupList()
    {
        $vehicles = TripTicketVehicle::orderBy('plate_no', 'asc')->get(['id', 'plate_no']);

        return response()->json($vehicles);
    }
}