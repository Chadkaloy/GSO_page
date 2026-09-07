<?php

namespace App\Http\Controllers;

use App\Models\PropertyReturnSlip;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PropertyReturnSlipController extends Controller
{
    use HasRelevanceSearch;
    /**
     * Render the View page via Inertia
     */
    public function index()
    {
        return Inertia::render('PropertyReturnSlip/Index');
    }

    /**
     * Data Table Pipeline: Serves requests with Pagination, Searching, and Sorting
     */
    public function list(Request $request)
    {
        $query = PropertyReturnSlip::with('purpose');

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['LGU_Name', 'Descrp', 'ParNo', 'Prop_Number', 'Name_of_Enduser']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Store record payload into database table storage
     */
    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'LGU_Name'             => 'required|string|max:250',
            'PurposeID'            => 'required|integer|exists:prs_purpose_dictionary,ID',
            'Qty'                  => 'required|integer',
            'Unit'                 => 'required|string|max:25',
            'Descrp'               => 'required|string|max:200',
            'Serial_Num'           => 'required|string|max:50',
            'Prop_Number'          => 'required|string|max:50',
            'ParNo'                => 'required|string|max:50',
            'Name_of_Enduser'      => 'required|string|max:50',
            'Unit_Value'           => 'required|integer',
            'Total_Value'          => 'required|integer',
            'Status'               => 'required|string|max:50',
            'ReceiveBy_Name'       => 'required|string|max:50',
            'ReceiveBy_Position'   => 'required|string|max:50',
            'ReceiveBy_Date'       => 'required|date',
            'ReceiveFrom_Name'     => 'required|string|max:50',
            'ReceiveFrom_Position' => 'required|string|max:50',
            'ReceiveFrom_Date'     => 'required|date',
        ]);

        $slip = PropertyReturnSlip::create($validated);

        LocationController::remember($validated['LGU_Name'] ?? null);

        return response()->json([
            'message' => 'Property Return slip created successfully!',
            'slip'    => $slip->load('purpose')
        ], 201);
    }

    /**
     * Fetch a specific object record for populate/edit state
     */
    public function show($id)
    {
        $slip = PropertyReturnSlip::with('purpose')->findOrFail($id);
        return response()->json($slip);
    }

    /**
     * Update existing target row record
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $slip = PropertyReturnSlip::findOrFail($id);
        
        $validated = $request->validate([
            'LGU_Name'             => 'sometimes|string|max:250',
            'PurposeID'            => 'sometimes|integer|exists:prs_purpose_dictionary,ID',
            'Qty'                  => 'sometimes|integer',
            'Unit'                 => 'sometimes|string|max:25',
            'Descrp'               => 'sometimes|string|max:200',
            'Serial_Num'           => 'sometimes|string|max:50',
            'Prop_Number'          => 'sometimes|string|max:50',
            'ParNo'                => 'sometimes|string|max:50',
            'Name_of_Enduser'      => 'sometimes|string|max:50',
            'Unit_Value'           => 'sometimes|integer',
            'Total_Value'          => 'sometimes|integer',
            'Status'               => 'sometimes|string|max:50',
            'ReceiveBy_Name'       => 'sometimes|string|max:50',
            'ReceiveBy_Position'   => 'sometimes|string|max:50',
            'ReceiveBy_Date'       => 'sometimes|date',
            'ReceiveFrom_Name'     => 'sometimes|string|max:50',
            'ReceiveFrom_Position' => 'sometimes|string|max:50',
            'ReceiveFrom_Date'     => 'sometimes|date',
        ]);

        $slip->update($validated);

        if (isset($validated['LGU_Name'])) {
            LocationController::remember($validated['LGU_Name']);
        }

        return response()->json([
            'message' => 'Property Return slip updated successfully!',
            'slip'    => $slip->fresh()->load('purpose')
        ]);
    }

    /**
     * Drop target row record out of table storage
     */
    public function destroy($id)
    {
        Gate::authorize('delete');

        $slip = PropertyReturnSlip::findOrFail($id);
        $slip->delete();

        return response()->json(['message' => 'Property Return slip records deleted successfully.'], 200);
    }
}