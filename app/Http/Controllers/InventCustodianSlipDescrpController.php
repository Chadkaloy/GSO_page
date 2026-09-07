<?php

namespace App\Http\Controllers;

use App\Models\InventCustodianSlipDescrp;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InventCustodianSlipDescrpController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Render listing workspace layout view via Inertia System
     */
    public function index()
    {
        return Inertia::render('CustodianSlipDescrp/Index');
    }

    /**
     * Dynamic paginated lookup matching criteria parameters
     */
    public function list(Request $request)
    {
        $query = InventCustodianSlipDescrp::with('custodianSlip:id,Invent_Item_No');

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['Descrp', 'Invent_Item_No']);
        }

        if ($request->has('icsID') && !empty($request->input('icsID'))) {
            $query->where('icsID', $request->input('icsID'));
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Enforce column length rules: Descrp (VARCHAR 50) & Invent_Item_No (VARCHAR 50)
     */
    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'icsID'          => 'required|integer|exists:invent_custodian_slip,id',
            'Descrp'         => 'required|string|max:50',
            'Invent_Item_No' => 'required|string|max:50',
        ]);

        $description = InventCustodianSlipDescrp::create($validated);

        return response()->json([
            'message'     => 'Custodian slip specification mapped successfully!',
            'description' => $description->load('custodianSlip:id,Invent_Item_No')
        ], 201);
    }

    /**
     * Isolate a single row item configuration 
     */
    public function show($id)
    {
        $description = InventCustodianSlipDescrp::with('custodianSlip:id,Invent_Item_No')->findOrFail($id);
        return response()->json($description);
    }

    /**
     * Safely patch structural metadata constraints
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $description = InventCustodianSlipDescrp::findOrFail($id);
        
        $validated = $request->validate([
            'icsID'          => 'sometimes|integer|exists:invent_custodian_slip,id',
            'Descrp'         => 'sometimes|string|max:50',
            'Invent_Item_No' => 'sometimes|string|max:50',
        ]);

        $description->update($validated);

        return response()->json([
            'message'     => 'Custodian item parameters updated successfully.',
            'description' => $description->fresh()->load('custodianSlip:id,Invent_Item_No')
        ]);
    }

    /**
     * Clear description row safely
     */
    public function destroy($id)
    {
        Gate::authorize('delete');

        $description = InventCustodianSlipDescrp::findOrFail($id);
        $description->delete();

        return response()->json(['message' => 'Specification row stripped out successfully.'], 200);
    }
}