<?php

namespace App\Http\Controllers;

use App\Models\TripTicketDriver;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class TripTicketDriverController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Render the initial frontend index view.
     */
    public function index(): Response
    {
        return Inertia::render('TripTicketDriver/Index');
    }

    /**
     * Server-side processing engine for ReusableDataTable requests.
     */
    public function list(Request $request): JsonResponse
    {
        $query = TripTicketDriver::query();

        // Handle Search Terms
        if ($search = $request->input('search')) {
            $this->applyRelevanceSearch($query, $search, ['driver_code', 'full_name', 'license_no', 'contact_no']);
        }

        // Handle Column Sorting Matrix
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Fetch paginated resource stream
        $perPage = $request->input('per_page', 10);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    /**
     * Store a newly created driver resource.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'driver_code' => 'required|string|max:20|unique:trip_ticket_drivers,driver_code',
            'full_name' => 'required|string|max:100',
            'license_no' => 'required|string|max:50',
            'license_expiry' => 'nullable|date',
            'contact_no' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200',
            'status' => 'required|string|max:20',
            'remarks' => 'nullable|string',
        ]);

        $driver = TripTicketDriver::create($validated);

        return response()->json($driver, 201);
    }

    /**
     * Retrieve individual entity attributes for processing modifications.
     */
    public function show(TripTicketDriver $TripTicketDriver): JsonResponse
    {
        return response()->json($TripTicketDriver);
    }

    /**
     * Update the specified driver resource.
     */
    public function update(Request $request, TripTicketDriver $TripTicketDriver): JsonResponse
    {
        Gate::authorize('edit');

        $validated = $request->validate([
            'driver_code' => 'required|string|max:20|unique:trip_ticket_drivers,driver_code,' . $TripTicketDriver->id,
            'full_name' => 'required|string|max:100',
            'license_no' => 'required|string|max:50',
            'license_expiry' => 'nullable|date',
            'contact_no' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200',
            'status' => 'required|string|max:20',
            'remarks' => 'nullable|string',
        ]);

        $TripTicketDriver->update($validated);

        return response()->json($TripTicketDriver);
    }

    /**
     * Remove the specified driver resource from storage.
     */
    public function destroy(TripTicketDriver $TripTicketDriver): JsonResponse
    {
        Gate::authorize('delete');

        $TripTicketDriver->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Lightweight lookup list for dropdowns: only Active drivers, only the
     * columns needed for a select (id + full_name). Mirrors
     * TripTicketVehicleController::getAvailableVehicles().
     *
     * NOTE: assumes 'Active' is the status value your data actually uses
     * for drivers eligible to be assigned. If your status column uses
     * something else (e.g. 'Available', 'On Duty'), let me know and I'll
     * adjust this where() clause.
     */
    public function getAvailableDrivers(): JsonResponse
    {
        $drivers = TripTicketDriver::where('status', 'Active')
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return response()->json($drivers);
    }
}