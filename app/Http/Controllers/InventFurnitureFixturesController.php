<?php

namespace App\Http\Controllers;

use App\Models\InventFurnitureFixtures;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InventFurnitureFixturesController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return Inertia::render('FurnitureFixtures/Index');
    }

    public function list(Request $request)
    {
        $query = InventFurnitureFixtures::query();

        // Dynamic query parsing over text metadata columns
        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['Descrp', 'PropNo', 'accCode', 'AccPerson']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'accCode'            => 'required|string|max:50',
            'ParNo'              => 'required|integer',
            'Qty'                => 'required|integer',
            'Unit'               => 'required|string|max:100',
            'Descrp'             => 'required|string|max:250',
            'UnitCost'           => 'required|integer',
            'TotalCost'          => 'required|integer',
            'PropNo'             => 'required|string|max:50',
            'AccPerson'          => 'required|string|max:200',
            'Designation_office' => 'required|string|max:100',
            'dateRelease'        => 'required|date_format:Y-m-d',
            'Supplier'           => 'required|string|max:150',
            'Remarks'            => 'required|string|max:150',
        ]);

        $item = InventFurnitureFixtures::create($validated);

        return response()->json([
            'message' => 'Furniture & fixture record created successfully!',
            'furniture_fixture' => $item
        ], 201);
    }

    public function show($id)
    {
        $item = InventFurnitureFixtures::findOrFail($id);
        return response()->json($item);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $item = InventFurnitureFixtures::findOrFail($id);
        
        $validated = $request->validate([
            'accCode'            => 'sometimes|string|max:50',
            'ParNo'              => 'sometimes|integer',
            'Qty'                => 'sometimes|integer',
            'Unit'               => 'sometimes|string|max:100',
            'Descrp'             => 'sometimes|string|max:250',
            'UnitCost'           => 'sometimes|integer',
            'TotalCost'          => 'sometimes|integer',
            'PropNo'             => 'sometimes|string|max:50',
            'AccPerson'          => 'sometimes|string|max:200',
            'Designation_office' => 'sometimes|string|max:100',
            'dateRelease'        => 'sometimes|date_format:Y-m-d',
            'Supplier'           => 'sometimes|string|max:150',
            'Remarks'            => 'sometimes|string|max:150',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Furniture & fixture record synchronized successfully.',
            'furniture_fixture' => $item->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $item = InventFurnitureFixtures::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Furniture & fixture entry removed successfully.'], 200);
    }
}