<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Lightweight suggestion endpoint for location-autocomplete inputs
     * (Trip Ticket Destination, Employee Account Address, Property Return
     * Slip LGU Name). Returns matching names only, capped to keep the
     * dropdown short and fast.
     */
    public function search(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $query = Location::query();

        if ($search !== '') {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        return response()->json(
            $query->orderBy('name')->limit(10)->pluck('name')
        );
    }

    /**
     * Caches a typed location value so it shows up as a suggestion next
     * time. Called from other controllers (Trip Ticket, Employee Account,
     * Property Return Slip) right after a successful store/update — never
     * exposed as its own route.
     *
     * Relies on firstOrCreate()'s exact match on 'name', which is
     * case-insensitive under MySQL's default collation (utf8mb4_general_ci
     * / utf8mb4_0900_ai_ci) — "Malaybalay" and "malaybalay" are treated as
     * the same row, so duplicates aren't created just from casing.
     */
    public static function remember(?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        Location::firstOrCreate(['name' => $value]);
    }
}