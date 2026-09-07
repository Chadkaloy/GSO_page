<?php

namespace App\Http\Controllers;

use App\Models\TripTicketRecord;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class TripTicketController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Display the index view via Inertia.
     */
    public function index()
    {
        return inertia('TripTicket/Index');
    }

    /**
     * Handle Server-Side Paginated Datatable Data.
     */
    public function list(Request $request)
    {
        // Eager-load the vehicle's capacity + a count of assigned passengers so
        // the table can display "available seats" per trip without N+1 queries.
        $query = TripTicketRecord::query()
            ->with('vehicle:id,plate_no,capacity')
            ->withCount('passengers');

        if ($request->has('search') && !empty($request->search)) {
            $this->applyRelevanceSearch($query, $request->search, ['trip_no', 'requester_name', 'destination', 'status']);
        }

        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->input('perPage', 10);
        $paginatedData = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedData->items(),
            'total' => $paginatedData->total(),
            'current_page' => $paginatedData->currentPage(),
            'per_page' => $paginatedData->perPage(),
            'last_page' => $paginatedData->lastPage(),
        ]);
    }

    /**
     * Lightweight lookup list for dropdowns: only Pending trips, only the
     * columns needed to populate a select (id + trip_no). Kept separate
     * from list() so we don't drag pagination/search concerns into a
     * simple dropdown data source.
     */
    public function pendingList()
    {
        $trips = TripTicketRecord::query()
            ->where('status', 'Pending')
            ->with('vehicle:id,plate_no,capacity')
            ->withCount('passengers')
            ->orderBy('trip_no')
            ->get(['id', 'trip_no', 'vehicle_id']);

        return response()->json($trips);
    }

    /**
     * Same as pendingList() but for Approved trips — used by the Trip Ticket
     * Approval page's "Completed" decision, since only an already-Approved
     * trip can be marked Completed.
     */
    public function approvedList()
    {
        $trips = TripTicketRecord::query()
            ->where('status', 'Approved')
            ->orderBy('trip_no')
            ->get(['id', 'trip_no']);

        return response()->json($trips);
    }

    /**
     * Store a newly created trip ticket.
     */
    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'trip_no'          => 'required|string|max:50|unique:trip_ticket_record,trip_no',
            'date_requested'   => 'required',
            'requester_name'   => 'required|string|max:100',
            'requester_office' => 'required|string|max:100',
            'destination'      => 'required|string|max:200',
            'purpose'          => 'required|string',
            'time_departure'   => 'required',
            'time_return'      => 'nullable',
            'vehicle_id'       => 'required|integer',
            'driver_id'        => 'required|integer',
            'passenger_count'  => 'required|integer|min:0',
            'remarks'          => 'nullable|string',
        ]);

        try {
            $validated['date_requested'] = Carbon::parse($validated['date_requested'])->format('Y-m-d');
            $validated['time_departure'] = $this->parseUIDatetime($validated['time_departure']);
            
            if (!empty($validated['time_return'])) {
                $validated['time_return'] = $this->parseUIDatetime($validated['time_return']);
            } else {
                $validated['time_return'] = null;
            }
        } catch (\Exception $e) {
            return response()->json(['errors' => ['time_departure' => ['Could not parse datetime selection structure safely.']]], 422);
        }

        // Status is never client-controlled on creation — every new trip
        // starts Pending and only moves via the Trip Ticket Approval workflow.
        $validated['status'] = 'Pending';

        // created_by must be an emp_accounts_record.accID, NOT a users.id —
        // those are two separate tables/sequences. Resolve the logged-in
        // user's linked employee record rather than assuming Auth::id()
        // happens to match an accID (it previously did only by coincidence
        // when both tables had exactly one row each).
        $employeeRecord = Auth::user()?->employeeRecord;
        if (!$employeeRecord) {
            return response()->json([
                'errors' => [
                    'created_by' => ['Your account is not yet linked to an employee record. Ask an admin to assign you a role on the Employee Accounts page before creating trips.'],
                ],
            ], 422);
        }
        $validated['created_by'] = $employeeRecord->accID;

        $record = TripTicketRecord::create($validated);

        LocationController::remember($validated['destination'] ?? null);

        return response()->json($record, 201);
    }

    /**
     * Fetch a single ticket record. Also eager-loads driver + passenger
     * names (in addition to vehicle) since this endpoint feeds both the
     * edit form and the printable Vehicle Trip Ticket.
     */
    public function show($id)
    {
        $record = TripTicketRecord::with([
                'vehicle:id,plate_no,capacity',
                'driver:id,full_name',
                'passengers:id,trip_id,passenger_name',
            ])
            ->withCount('passengers')
            ->findOrFail($id);

        return response()->json($record);
    }

    /**
     * Update an existing trip ticket record.
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $record = TripTicketRecord::findOrFail($id);

        $validated = $request->validate([
            'trip_no'          => 'required|string|max:50|unique:trip_ticket_record,trip_no,' . $id,
            'date_requested'   => 'required',
            'requester_name'   => 'required|string|max:100',
            'requester_office' => 'required|string|max:100',
            'destination'      => 'required|string|max:200',
            'purpose'          => 'required|string',
            'time_departure'   => 'required',
            'time_return'      => 'nullable',
            'vehicle_id'       => 'required|integer',
            'driver_id'        => 'required|integer',
            'passenger_count'  => 'required|integer|min:0',
            'remarks'          => 'nullable|string',
        ]);

        try {
            $validated['date_requested'] = Carbon::parse($validated['date_requested'])->format('Y-m-d');
            $validated['time_departure'] = $this->parseUIDatetime($validated['time_departure']);
            
            if (!empty($validated['time_return'])) {
                $validated['time_return'] = $this->parseUIDatetime($validated['time_return']);
            } else {
                $validated['time_return'] = null;
            }
        } catch (\Exception $e) {
            return response()->json(['errors' => ['time_departure' => ['Could not parse datetime selection structure safely.']]], 422);
        }

        $record->update($validated);

        if (isset($validated['destination'])) {
            LocationController::remember($validated['destination']);
        }

        return response()->json($record);
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id)
    {
        Gate::authorize('delete');

        $record = TripTicketRecord::findOrFail($id);
        $record->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Helper to reliably read UI raw picker layout string
     */
    private function parseUIDatetime($dateString)
    {
        if (str_contains($dateString, 'AM') || str_contains($dateString, 'PM')) {
            $cleaned = preg_replace('/\s+/', ' ', trim($dateString));
            return Carbon::createFromFormat('m/d/Y h:i A', $cleaned)->format('Y-m-d H:i:s');
        }
        
        return Carbon::parse(str_replace('T', ' ', $dateString))->format('Y-m-d H:i:s');
    }
}