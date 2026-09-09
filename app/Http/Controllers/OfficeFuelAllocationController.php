<?php

namespace App\Http\Controllers;

use App\Models\OfficeFuelAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfficeFuelAllocationController extends Controller
{
    public function index()
    {
        return inertia('OfficeFuelAllocation/Index');
    }

    public function list(Request $request)
    {
        // No eager-loaded office relation here on purpose — office_dictionary's
        // exact display-name column hasn't been confirmed yet, and a wrong
        // column name in a JOIN would 500 the whole page. The frontend
        // resolves office_id -> office name using the existing /Office/lookup
        // endpoint instead, which fails soft (blank label) rather than hard.
        $query = OfficeFuelAllocation::query();

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'office_id'        => 'required|integer',
            'year'             => 'required|integer|min:2000|max:2100',
            'liters_allocated' => 'required|numeric|min:0',
        ]);

        $duplicate = OfficeFuelAllocation::where('office_id', $validated['office_id'])
            ->where('year', $validated['year'])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'errors' => [
                    'office_id' => ["This office already has a fuel allocation set for {$validated['year']}."],
                ],
            ], 422);
        }

        $allocation = OfficeFuelAllocation::create([
            'office_id'        => $validated['office_id'],
            'year'             => $validated['year'],
            'liters_allocated' => $validated['liters_allocated'],
            // Starts fully unspent — this is what trip creation will deduct from.
            'liters_remaining' => $validated['liters_allocated'],
        ]);

        return response()->json([
            'message'    => 'Fuel allocation added successfully!',
            'allocation' => $allocation,
        ], 201);
    }

    public function show($id)
    {
        $allocation = OfficeFuelAllocation::findOrFail($id);

        return response()->json($allocation);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $allocation = OfficeFuelAllocation::findOrFail($id);

        // office_id and year are intentionally NOT editable here — changing
        // which office/year an existing allocation belongs to would orphan
        // the meaning of any ledger transactions already tied to it (see
        // OfficeFuelAllocationTransaction). Only the granted total can be
        // adjusted — e.g. a mid-year top-up or correction.
        $validated = $request->validate([
            'liters_allocated' => 'required|numeric|min:0',
        ]);

        // Move liters_remaining by the same delta as the change in the
        // granted total, so whatever's already been spent stays spent —
        // e.g. 40/50L remaining, top up to 70L allocated -> 60/70L remaining.
        $delta = $validated['liters_allocated'] - (float) $allocation->liters_allocated;

        $allocation->liters_allocated = $validated['liters_allocated'];
        $allocation->liters_remaining = max(0, (float) $allocation->liters_remaining + $delta);
        $allocation->save();

        return response()->json([
            'message'    => 'Fuel allocation updated successfully!',
            'allocation' => $allocation->fresh(),
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $allocation = OfficeFuelAllocation::findOrFail($id);
        $allocation->delete();

        return response()->json(['message' => 'Fuel allocation removed successfully.'], 200);
    }
}