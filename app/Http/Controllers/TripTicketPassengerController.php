<?php

namespace App\Http\Controllers;

use App\Models\TripTicketPassenger;
use App\Models\TripTicketRecord;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TripTicketPassengerController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('TripTicketPassenger/Index');
    }

    public function list(Request $request)
    {
        // Eager-load only the columns we need from the parent trip so the
        // response can show trip_no without a second round trip.
        $query = TripTicketPassenger::with(['trip:id,trip_no']);

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $search = $request->input('searchtext');
            $query->where(function ($q) use ($search) {
                $q->where('passenger_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('office', 'LIKE', '%' . $search . '%')
                  ->orWhere('contact_no', 'LIKE', '%' . $search . '%')
                  // Let the same search box also match the parent trip's number.
                  ->orWhereHas('trip', function ($tq) use ($search) {
                      $tq->where('trip_no', 'LIKE', '%' . $search . '%');
                  });
            });

            // Ranks by closeness on this table's own columns. A match that
            // only came from the related trip_no won't be ranked (that would
            // need a join), but it still appears in the results — just not
            // pulled to the top based on trip_no closeness specifically.
            $this->applyRelevanceOrder($query, $search, ['passenger_name', 'office', 'contact_no']);
        }

        // Filter by the actual database foreign key column name: trip_id
        if ($request->has('trip_id')) {
            $query->where('trip_id', $request->input('trip_id'));
        }

        // Allow filtering/looking up passengers by the human-readable trip_no too,
        // even though the column stored on this table is trip_id.
        if ($request->has('trip_no') && !empty($request->input('trip_no'))) {
            $query->whereHas('trip', function ($tq) use ($request) {
                $tq->where('trip_no', $request->input('trip_no'));
            });
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $sortField = $request->input('sort_field');
            $sortDirection = $request->input('sort_direction');

            if ($sortField === 'trip_no') {
                // trip_no lives on the related table, so ordering needs a join
                // rather than a plain orderBy (which only works on this table's columns).
                $query->join('trip_ticket_record', 'trip_ticket_passengers.trip_id', '=', 'trip_ticket_record.id')
                      ->orderBy('trip_ticket_record.trip_no', $sortDirection)
                      ->select('trip_ticket_passengers.*');
            } else {
                $query->orderBy($sortField, $sortDirection);
            }
        } else {
            $query->orderBy('passenger_name', 'asc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Returns passengers grouped by trip — one entry per trip with its full
     * passenger list nested inside — for the collapsed/expandable table view.
     * Pagination happens at the trip level, not the passenger level.
     */
    public function listGrouped(Request $request)
    {
        $search = $request->input('searchtext');

        // Stage 1: find which trip_ids belong on this page, paginated and
        // sorted at the trip level (via a join purely for trip_no ordering).
        $tripIdsQuery = TripTicketPassenger::query()
            ->select('trip_ticket_passengers.trip_id')
            ->distinct()
            ->join('trip_ticket_record', 'trip_ticket_passengers.trip_id', '=', 'trip_ticket_record.id')
            ->addSelect('trip_ticket_record.trip_no');

        if (!empty($search)) {
            $tripIdsQuery->where(function ($q) use ($search) {
                $q->where('trip_ticket_passengers.passenger_name', 'LIKE', "%{$search}%")
                  ->orWhere('trip_ticket_passengers.office', 'LIKE', "%{$search}%")
                  ->orWhere('trip_ticket_passengers.contact_no', 'LIKE', "%{$search}%")
                  ->orWhere('trip_ticket_record.trip_no', 'LIKE', "%{$search}%");
            });
        }

        $sortDirection = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $tripIdsQuery->orderBy('trip_ticket_record.trip_no', $sortDirection);

        $paginated = $tripIdsQuery->paginate($request->input('per_page', 10));

        $tripIds = collect($paginated->items())->pluck('trip_id')->values();

        // Stage 2: fetch the full passenger list for exactly those trip_ids.
        $passengers = TripTicketPassenger::with('trip:id,trip_no')
            ->whereIn('trip_id', $tripIds)
            ->orderBy('passenger_name')
            ->get();

        $groupedByTripId = $passengers->groupBy('trip_id');

        // Rebuild in the trip_id order established by pagination/sorting above.
        $data = $tripIds->map(function ($tripId) use ($groupedByTripId) {
            $group = $groupedByTripId->get($tripId);
            if (!$group || $group->isEmpty()) {
                return null;
            }

            $first = $group->first();

            return [
                'trip_id' => $tripId,
                'trip_no' => $first->trip->trip_no ?? null,
                'passengers' => $group->map(fn ($p) => [
                    'id'             => $p->id,
                    'passenger_name' => $p->passenger_name,
                    'office'         => $p->office,
                    'contact_no'     => $p->contact_no,
                ])->values(),
            ];
        })->filter()->values();

        return response()->json([
            'data'         => $data,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        // 1. Validate the incoming request parameter matching the database table name (trip_ticket_record)
        $validated = $request->validate([
            'trip_id'        => 'required|exists:trip_ticket_record,id',
            'passenger_name' => 'required|string|max:100',
            'office'         => 'nullable|string|max:100',
            'contact_no'     => 'nullable|string|max:20',
        ]);

        // Enforce the assigned vehicle's seat capacity server-side — this is
        // the actual guard; any frontend dropdown filtering is just UX sugar
        // on top of this and must never be relied on alone.
        $capacityError = $this->checkSeatCapacity($validated['trip_id']);
        if ($capacityError) {
            return $capacityError;
        }

        // 2. Safely mass-assign because keys now align perfectly with your model's $fillable array
        $passenger = TripTicketPassenger::create($validated);

        return response()->json([
            'message'   => 'Passenger assigned to trip successfully!',
            'passenger' => $passenger->load('trip:id,trip_no')
        ], 201);
    }

    /**
     * Bulk-assign multiple passengers to the same trip in a single request.
     * Used by the "Add Another Passenger" repeatable form on the frontend.
     * Re-validates seat capacity server-side regardless of what the frontend
     * already limited the user to — the frontend counter is UX only.
     */
    public function storeBulk(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'trip_id'                     => 'required|exists:trip_ticket_record,id',
            'passengers'                  => 'required|array|min:1',
            'passengers.*.passenger_name' => 'required|string|max:100',
            'passengers.*.office'         => 'nullable|string|max:100',
            'passengers.*.contact_no'     => 'nullable|string|max:20',
        ]);

        $tripId = $validated['trip_id'];
        $passengers = $validated['passengers'];

        // Guard against a batch that alone (or combined with existing bookings)
        // exceeds the vehicle's seat capacity.
        $trip = TripTicketRecord::with('vehicle:id,plate_no,capacity')->find($tripId);

        if ($trip && $trip->vehicle) {
            $currentCount = TripTicketPassenger::where('trip_id', $tripId)->count();
            $available = $trip->vehicle->capacity - $currentCount;

            if (count($passengers) > $available) {
                return response()->json([
                    'errors' => [
                        'trip_id' => [
                            "Trip {$trip->trip_no}'s vehicle ({$trip->vehicle->plate_no}) only has {$available} seat(s) left, but " . count($passengers) . ' passenger(s) were submitted.',
                        ],
                    ],
                ], 422);
            }
        }

        $createdCount = 0;

        // Wrapped in a transaction so a batch either saves completely or not
        // at all — no partial manifest if something fails mid-loop.
        DB::transaction(function () use ($tripId, $passengers, &$createdCount) {
            foreach ($passengers as $passenger) {
                TripTicketPassenger::create([
                    'trip_id'        => $tripId,
                    'passenger_name' => $passenger['passenger_name'],
                    'office'         => $passenger['office'] ?? null,
                    'contact_no'     => $passenger['contact_no'] ?? null,
                ]);
                $createdCount++;
            }
        });

        return response()->json([
            'message' => "{$createdCount} passenger(s) assigned to trip successfully!",
        ], 201);
    }

    public function show($id)
    {
        $passenger = TripTicketPassenger::with('trip:id,trip_no')->findOrFail($id);
        return response()->json($passenger);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $passenger = TripTicketPassenger::findOrFail($id);

        $validated = $request->validate([
            'trip_id'        => 'sometimes|exists:trip_ticket_record,id',
            'passenger_name' => 'sometimes|string|max:100',
            'office'         => 'nullable|string|max:100',
            'contact_no'     => 'nullable|string|max:20',
        ]);

        // Only re-check capacity if the passenger is actually being moved to
        // a different trip. Exclude this passenger's own current row from
        // the count so re-saving them on the same trip never false-positives.
        if (isset($validated['trip_id']) && (int) $validated['trip_id'] !== (int) $passenger->trip_id) {
            $capacityError = $this->checkSeatCapacity($validated['trip_id'], $passenger->id);
            if ($capacityError) {
                return $capacityError;
            }
        }

        $passenger->update($validated);

        return response()->json([
            'message'   => 'Passenger manifest record updated successfully!',
            'passenger' => $passenger->fresh()->load('trip:id,trip_no')
        ]);
    }

    /**
     * Returns a 422 JsonResponse if the trip's assigned vehicle is already
     * at (or over) capacity, or null if there's room / no vehicle assigned
     * (a trip with no vehicle yet has nothing to cap passengers against).
     */
    private function checkSeatCapacity(int $tripId, ?int $excludePassengerId = null)
    {
        $trip = TripTicketRecord::with('vehicle:id,plate_no,capacity')->find($tripId);

        if (!$trip || !$trip->vehicle) {
            return null;
        }

        $currentCount = TripTicketPassenger::where('trip_id', $tripId)
            ->when($excludePassengerId, fn ($q) => $q->where('id', '!=', $excludePassengerId))
            ->count();

        if ($currentCount >= $trip->vehicle->capacity) {
            return response()->json([
                'errors' => [
                    'trip_id' => [
                        "Trip {$trip->trip_no}'s vehicle ({$trip->vehicle->plate_no}) is already full ({$trip->vehicle->capacity} seat(s)). Remove a passenger or assign a larger vehicle first.",
                    ],
                ],
            ], 422);
        }

        return null;
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $passenger = TripTicketPassenger::findOrFail($id);
        $passenger->delete();

        return response()->json(['message' => 'Passenger removed from manifest successfully.'], 200);
    }
}