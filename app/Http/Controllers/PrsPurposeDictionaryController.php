<?php

namespace App\Http\Controllers;

use App\Models\PrsPurposeDictionary;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PrsPurposeDictionaryController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('PrsPurposeDictionary/Index');
    }

    // Required by ReusableDataTable to fetch table rows dynamically via Axios
    public function list(Request $request)
    {
        $query = PrsPurposeDictionary::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['Purpose_Type']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('ID', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Lightweight lookup list for parent-selection dropdowns elsewhere in
     * the system (e.g. the Property Return Slip form's Purpose ID field) —
     * every purpose type shown by ID + label.
     */
    public function lookupList()
    {
        return response()->json(
            PrsPurposeDictionary::orderBy('Purpose_Type')->get(['ID', 'Purpose_Type'])
        );
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'Purpose_Type' => 'required|string|max:25|unique:prs_purpose_dictionary,Purpose_Type',
        ]);

        $purpose = PrsPurposeDictionary::create($validated);

        return response()->json([
            'message' => 'Purpose Type created successfully.',
            'data' => $purpose
        ], 201);
    }

    public function show($id)
    {
        $purpose = PrsPurposeDictionary::findOrFail($id);
        return response()->json($purpose);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $purpose = PrsPurposeDictionary::findOrFail($id);

        $validated = $request->validate([
            'Purpose_Type' => [
                'required',
                'string',
                'max:25',
                Rule::unique('prs_purpose_dictionary', 'Purpose_Type')->ignore($id, 'ID')
            ],
        ]);

        $purpose->update($validated);

        return response()->json([
            'message' => 'Purpose Type updated successfully.',
            'data' => $purpose
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $purpose = PrsPurposeDictionary::findOrFail($id);
        $purpose->delete();

        return response()->json([
            'message' => 'Purpose Type deleted successfully.'
        ]);
    }
}