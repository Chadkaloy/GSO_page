<?php

namespace App\Http\Controllers;

use App\Models\OfficeDictionary;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OfficeDictionaryController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('Office/Index');
    }

    public function list(Request $request)
    {
        $query = OfficeDictionary::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['officeName', 'officeCode']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('officeName', 'asc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'officeName' => 'required|string|max:50|unique:office_dictionary,officeName',
            'officeCode' => 'required|string|max:100|unique:office_dictionary,officeCode',
        ]);

        $office = OfficeDictionary::create($validated);

        return response()->json([
            'message' => 'Office created successfully!',
            'office' => $office
        ], 201);
    }

    public function show($id)
    {
        $office = OfficeDictionary::findOrFail($id);
        return response()->json($office);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $office = OfficeDictionary::findOrFail($id);

        $validated = $request->validate([
            'officeName' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('office_dictionary', 'officeName')->ignore($id)
            ],
            'officeCode' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('office_dictionary', 'officeCode')->ignore($id)
            ],
        ]);

        $office->update($validated);

        return response()->json([
            'message' => 'Office updated successfully!',
            'office' => $office->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $office = OfficeDictionary::findOrFail($id);
        $office->delete();

        return response()->json(['message' => 'Office deleted successfully.'], 200);
    }

    public function getAllOffices()
    {
        $offices = OfficeDictionary::orderBy('officeName', 'asc')->get(['id', 'officeName', 'officeCode']);
        return response()->json($offices);
    }
}