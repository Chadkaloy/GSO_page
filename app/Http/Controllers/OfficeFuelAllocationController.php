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
            'office_id'        => 'required|integer|unique:office_fuel_allocations,office_id',
            'liters_allocated' => 'required|numeric|min:0',
        ]);

        $allocation = OfficeFuelAllocation::create($validated);

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

        $validated = $request->validate([
            'office_id'        => 'sometimes|integer|unique:office_fuel_allocations,office_id,' . $id,
            'liters_allocated' => 'sometimes|numeric|min:0',
        ]);

        $allocation->update($validated);

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
