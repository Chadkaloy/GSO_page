<?php

namespace App\Http\Controllers;

use App\Models\InventCustodianSlip;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InventCustodianSlipController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Render index page layout via Inertia View System
     */
    public function index()
    {
        return Inertia::render('CustodianSlip/Index');
    }

    /**
     * Data table compilation handling server-side parsing engine
     */
    public function list(Request $request)
    {
        $query = InventCustodianSlip::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['Descrp', 'Invent_Item_No', 'ReceivedBy_Name', 'ReceivedFrom_Name']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Safely insert form inputs checking database strict data lengths
     */
    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'Qty'                   => 'required|integer',
            'Unit'                  => 'required|string|max:50',
            'Descrp'                => 'required|string|max:250',
            'Invent_Item_No'        => 'required|string|max:50',
            'Ez_Useful_Life'        => 'required|string|max:50',
            'ReceivedBy_Name'       => 'required|string|max:50',
            'ReceivedBy_Position'   => 'required|string|max:50',
            'ReceiveBy_Date'        => 'required|date',
            'ReceivedFrom_Name'     => 'required|string|max:50',
            'ReceivedFrom_Position' => 'required|string|max:50',
            'ReceiveFrom_Date'      => 'required|date',
            'ICS'                   => 'required|integer',
        ]);

        $slip = InventCustodianSlip::create($validated);

        return response()->json([
            'message' => 'Custodian slip generated successfully!',
            'slip'    => $slip
        ], 201);
    }

    /**
     * Expose specific target models for edit parameters
     */
    public function show($id)
    {
        $slip = InventCustodianSlip::findOrFail($id);
        return response()->json($slip);
    }

    /**
     * Update validation layers matching column parameters exactly
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $slip = InventCustodianSlip::findOrFail($id);

        $validated = $request->validate([
            'Qty'                   => 'sometimes|integer',
            'Unit'                  => 'sometimes|string|max:50',
            'Descrp'                => 'sometimes|string|max:250',
            'Invent_Item_No'        => 'sometimes|string|max:50',
            'Ez_Useful_Life'        => 'sometimes|string|max:50',
            'ReceivedBy_Name'       => 'sometimes|string|max:50',
            'ReceivedBy_Position'   => 'sometimes|string|max:50',
            'ReceiveBy_Date'        => 'sometimes|date',
            'ReceivedFrom_Name'     => 'sometimes|string|max:50',
            'ReceivedFrom_Position' => 'sometimes|string|max:50',
            'ReceiveFrom_Date'      => 'sometimes|date',
            'ICS'                   => 'sometimes|integer',
        ]);

        $slip->update($validated);

        return response()->json([
            'message' => 'Custodian slip details updated successfully!',
            'slip'    => $slip->fresh()
        ]);
    }

    /**
     * Remove explicit custodian slip rows securely
     */
    public function destroy($id)
    {
        Gate::authorize('delete');

        $slip = InventCustodianSlip::findOrFail($id);
        $slip->delete();

        return response()->json(['message' => 'Custodian slip dropped out of records successfully.'], 200);
    }

    /**
     * Lightweight lookup list for the "Parent ICS" dropdown on the Custodian
     * Slip Item Description form — id (the actual FK target) + Invent_Item_No
     * (what gets displayed, per the request that the dropdown show the
     * inventory item number instead of the raw ICS id).
     */
    public function lookupList()
    {
        $slips = InventCustodianSlip::orderBy('Invent_Item_No')->get(['id', 'Invent_Item_No']);

        return response()->json($slips);
    }
}