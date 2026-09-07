<?php

namespace App\Http\Controllers;

use App\Models\BincardIssuedRecord;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BincardIssuedRecordController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('BincardIssued/Index');
    }

    public function list(Request $request)
    {
        $query = BincardIssuedRecord::with('bincard:id,Descrp,Supplier,PoNo');

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['itemCode', 'ItemSetID', 'recpnt']);
        }

        $sortField = $request->input('sort_field', 'issued_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'ItemSetID' => 'nullable|string|max:100',
            'itemCode' => 'required|string|max:100',
            'bin_ID' => 'required|integer',
            'recpnt' => 'required|string|max:150',
            'issued_date' => 'required|date',
            'qty' => 'required|numeric',
        ]);

        $record = BincardIssuedRecord::create($validated);

        return response()->json([
            'message' => 'Bincard Issued Record created successfully!',
            'bincard_issued' => $record
        ], 201);
    }

    public function show($id)
    {
        $record = BincardIssuedRecord::with('bincard:id,Descrp,Supplier,PoNo')->findOrFail($id);
        return response()->json($record);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $record = BincardIssuedRecord::findOrFail($id);
        
        $validated = $request->validate([
            'ItemSetID' => 'nullable|string|max:100',
            'itemCode' => 'sometimes|string|max:100',
            'bin_ID' => 'sometimes|integer',
            'recpnt' => 'sometimes|string|max:150',
            'issued_date' => 'sometimes|date',
            'qty' => 'sometimes|numeric',
        ]);

        $record->update($validated);

        return response()->json([
            'message' => 'Bincard Issued Record updated successfully!',
            'bincard_issued' => $record->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $record = BincardIssuedRecord::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Bincard Issued Record deleted successfully.'], 200);
    }
}