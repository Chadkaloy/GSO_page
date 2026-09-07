<?php

namespace App\Http\Controllers;

use App\Models\EmpPgcRecord;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmpPgcRecordController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('Pgc/Index');
    }

    public function list(Request $request)
    {
        $query = EmpPgcRecord::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['fullName', 'office', 'designation']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('fullName', 'asc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'fullName'    => 'required|string|max:150',
            'office'      => 'required|string|max:50',
            'designation' => 'required|string|max:100',
            'note'        => 'nullable|string|max:50',
        ]);

        $pgc = EmpPgcRecord::create($validated);

        return response()->json([
            'message' => 'PGC record created successfully!',
            'pgc' => $pgc
        ], 201);
    }

    public function show($id)
    {
        $pgc = EmpPgcRecord::findOrFail($id);
        return response()->json($pgc);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $pgc = EmpPgcRecord::findOrFail($id);

        $validated = $request->validate([
            'fullName'    => 'sometimes|string|max:150',
            'office'      => 'sometimes|string|max:50',
            'designation' => 'sometimes|string|max:100',
            'note'        => 'nullable|string|max:50',
        ]);

        $pgc->update($validated);

        return response()->json([
            'message' => 'PGC record updated successfully!',
            'pgc' => $pgc->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $pgc = EmpPgcRecord::findOrFail($id);
        $pgc->delete();

        return response()->json(['message' => 'PGC record deleted successfully.'], 200);
    }
}