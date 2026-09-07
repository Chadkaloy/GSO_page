<?php

namespace App\Http\Controllers;

use App\Models\PropertyAccountabilityReceipt;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PropertyAccountabilityReceiptController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Render the Index page view via Inertia
     */
    public function index()
    {
        return Inertia::render('PropertyReceipt/Index');
    }

    /**
     * Handle Server-Side Data Tables requests (Search, Sorting, Pagination)
     */
    public function list(Request $request)
    {
        $query = PropertyAccountabilityReceipt::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['Descrp', 'PropNo', 'PAR', 'ReceivedFrom_Name', 'ReceivedBy_Name']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Store a newly created record in storage
     */
    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'Qty'                   => 'required|integer',
            'Unit'                  => 'required|string|max:25',
            'Descrp'                => 'required|string|max:150',
            'PropNo'                => 'required|string|max:25',
            'ReceivedFrom_Name'     => 'required|string|max:50',
            'ReceivedFrom_Position' => 'required|string|max:50',
            'ReceivedFrom_Date'     => 'required|date',
            'ReceivedBy_Name'       => 'required|string|max:50',
            'ReceivedBy_Position'   => 'required|string|max:50',
            'ReceivedBy_Date'       => 'required|date',
            'PAR'                   => 'required|string|max:12',
        ]);

        $receipt = PropertyAccountabilityReceipt::create($validated);

        return response()->json([
            'message' => 'Property receipt created successfully!',
            'receipt' => $receipt
        ], 201);
    }

    /**
     * Display the specified record for editing
     */
    public function show($id)
    {
        $receipt = PropertyAccountabilityReceipt::findOrFail($id);
        return response()->json($receipt);
    }

    /**
     * Update the specified record in storage
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $receipt = PropertyAccountabilityReceipt::findOrFail($id);
        
        $validated = $request->validate([
            'Qty'                   => 'sometimes|integer',
            'Unit'                  => 'sometimes|string|max:25',
            'Descrp'                => 'sometimes|string|max:150',
            'PropNo'                => 'sometimes|string|max:25',
            'ReceivedFrom_Name'     => 'sometimes|string|max:50',
            'ReceivedFrom_Position' => 'sometimes|string|max:50',
            'ReceivedFrom_Date'     => 'sometimes|date',
            'ReceivedBy_Name'       => 'sometimes|string|max:50',
            'ReceivedBy_Position'   => 'sometimes|string|max:50',
            'ReceivedBy_Date'       => 'sometimes|date',
            'PAR'                   => 'sometimes|string|max:12',
        ]);

        $receipt->update($validated);

        return response()->json([
            'message' => 'Property receipt updated successfully!',
            'receipt' => $receipt->fresh()
        ]);
    }

    /**
     * Remove the specified record from storage
     */
    public function destroy($id)
    {
        Gate::authorize('delete');

        $receipt = PropertyAccountabilityReceipt::findOrFail($id);
        $receipt->delete();

        return response()->json(['message' => 'Property receipt deleted successfully.'], 200);
    }
}