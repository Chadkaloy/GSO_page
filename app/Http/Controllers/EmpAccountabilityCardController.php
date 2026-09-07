<?php

namespace App\Http\Controllers;

use App\Models\EmpAccountabilityCard;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmpAccountabilityCardController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Render the Index page view via Inertia
     */
    public function index()
    {
        return inertia('Accountability/Index');
    }

    /**
     * Handle Server-Side Data Tables requests (Search, Sorting, Pagination)
     */
    public function list(Request $request)
    {
        $query = EmpAccountabilityCard::with([
            'employee:accID,fullName',
            'inventoryItem:AC_COA_Cir_04-08,AC_Name(Old),AC_name(New)',
        ]);

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['ItemSetID', 'itemCode', 'Descrp', 'ParNo', 'SN', 'PropNo']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'desc'); // Order by latest by default
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Store a new record
     */
    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'Emp_ID'       => 'required|integer',
            'ItemSetID'    => 'required|string|max:25',
            'itemCode'     => 'required|string|max:25',
            'ParNo'        => 'required|string|max:50',
            'Qty'          => 'required|integer',
            'Unit'         => 'required|string|max:50',
            'Descrp'       => 'required|string|max:150',
            'SN'           => 'required|string|max:50',
            'PropNo'       => 'required|string|max:50',
            'Amount'       => 'required|numeric',
            'TransferTo'   => 'required|string|max:100',
            'Remarks'      => 'required|string|max:200',
            'DateTurnOver' => 'required|date',
        ]);

        $accountability = EmpAccountabilityCard::create($validated);

        return response()->json([
            'message' => 'Accountability card created successfully!',
            'accountability' => $accountability
        ], 201);
    }

    /**
     * Fetch a single record for editing
     */
    public function show($id)
    {
        $accountability = EmpAccountabilityCard::with([
            'employee:accID,fullName',
            'inventoryItem:AC_COA_Cir_04-08,AC_Name(Old),AC_name(New)',
        ])->findOrFail($id);
        return response()->json($accountability);
    }

    /**
     * Update an existing record
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $accountability = EmpAccountabilityCard::findOrFail($id);
        
        $validated = $request->validate([
            'Emp_ID'       => 'sometimes|integer',
            'ItemSetID'    => 'sometimes|string|max:25',
            'itemCode'     => 'sometimes|string|max:25',
            'ParNo'        => 'sometimes|string|max:50',
            'Qty'          => 'sometimes|integer',
            'Unit'         => 'sometimes|string|max:50',
            'Descrp'       => 'sometimes|string|max:150',
            'SN'           => 'sometimes|string|max:50',
            'PropNo'       => 'sometimes|string|max:50',
            'Amount'       => 'sometimes|numeric',
            'TransferTo'   => 'sometimes|string|max:100',
            'Remarks'      => 'sometimes|string|max:200',
            'DateTurnOver' => 'sometimes|date',
        ]);

        $accountability->update($validated);

        return response()->json([
            'message' => 'Accountability card updated successfully!',
            'accountability' => $accountability->fresh()
        ]);
    }

    /**
     * Destroy a record
     */
    public function destroy($id)
    {
        Gate::authorize('delete');

        $accountability = EmpAccountabilityCard::findOrFail($id);
        $accountability->delete();

        return response()->json(['message' => 'Accountability card deleted successfully.'], 200);
    }
}