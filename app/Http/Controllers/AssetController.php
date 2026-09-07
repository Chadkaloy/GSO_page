<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\User;
class AssetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::orderBy('name','asc')->get(['id', 'name']);
        $locations = Location::orderBy('name','asc')->get(['id', 'name']);
        $manufacturers = Manufacturer::orderBy('name','asc')->get(['id', 'name']);
        $users = User::orderBy('name','asc')->get(['id', 'name']);

        return inertia('Asset/Index', [
            'categories' => $categories,
            'locations' => $locations,
            'manufacturers' => $manufacturers,
            'users' => $users,
        ]);
    }

    public function list(Request $request)
    {
        $query = Asset::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $search = $request->input('searchtext');
            $query
                ->whereLike('name', '%'.$search.'%');
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('name', 'asc'); // Default sorting
        }

        $asset = AssetResource::collection(
            $query->orderBy('name', 'asc')->paginate($request->input('per_page', 5))
        );

        return $asset;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAssetRequest $request)
    {
        $validatedData = $request->validated();

        $asset = Asset::create($validatedData);

        return response()->json([
            'message' => 'Asset created successfully!',
            'asset' => $asset // Optionally return the created asset data
        ], 201); // 201 Created status code
    }

    /**
     * Display the specified resource.
     */
    public function show(Asset $asset)
    {
        $asset = Asset::with(['category', 'location', 'manufacturer', 'assignedTo'])->findOrFail($asset->id);

        if (!$asset) {
            return redirect()->back()->with('error', 'Asset not found.');
        }

        return response()->json($asset);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAssetRequest $request, Asset $asset)
    {
        $validatedData = $request->validated();
        \Log::info('UpdateAssetRequest validated data:', $validatedData);
        $asset->update($validatedData);

        return response()->json([
            'message' => 'Asset updated successfully!',
            'asset' => $asset->fresh() // Return the fresh, updated asset data
        ], 200); // 200 OK status code for successful updates
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $asset = Asset::findOrFail($id); // Find the asset or throw a 404 error
            $asset->delete(); // Delete the asset

            return response()->json(['message' => 'Asset deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete asset.'], 500);
        }
    }


}
