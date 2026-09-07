<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Manufacturer;
use App\Traits\HasRelevanceSearch;
use App\Http\Requests\StoreManufacturerRequest;
use App\Http\Requests\UpdateManufacturerRequest;
use App\Http\Resources\ManufacturerResource;
class ManufacturerController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('Manufacturer/Index');
    }

    public function list(Request $request)
    {
        $query = Manufacturer::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $search = $request->input('searchtext');
            $this->applyRelevanceSearch($query, $search, ['name']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('name', 'asc'); // Default sorting
        }

        $manufacturer = ManufacturerResource::collection(
            $query->orderBy('name', 'asc')->paginate($request->input('per_page', 5))
        );
        
        return $manufacturer;
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManufacturerRequest $request)
    {
        $validatedData = $request->validated();

        $manufacturer = Manufacturer::create($validatedData);

        return response()->json([
            'message' => 'Manufacturer created successfully!',
            'manufacturer' => $manufacturer // Optionally return the created manufacturer data
        ], 201); // 201 Created status code
    }
    /**
     * Display the specified resource.
     */
    public function show(Manufacturer $manufacturer)
    {
        $manufacturer = Manufacturer::findOrFail($manufacturer->id);

        if (!$manufacturer) {
            return redirect()->back()->with('error', 'Manufacturer not found.');
        }

        return response()->json($manufacturer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateManufacturerRequest $request, Manufacturer $manufacturer)
    {
        $validatedData = $request->validated();

        $manufacturer->update($validatedData);

        return response()->json([
            'message' => 'Manufacturer updated successfully!',
            'manufacturer' => $manufacturer->fresh() // Return the fresh, updated manu$manufacturer data
        ], 200); // 200 OK status code for successful updates
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $manufacturer = Manufacturer::findOrFail($id); // Find the manufacturer or throw a 404 error
            $manufacturer->delete(); // Delete the manufacturer

            return response()->json(['message' => 'Manufacturer deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete manufacturer.'], 500);
        }
    }
}