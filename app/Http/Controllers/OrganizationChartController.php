<?php

namespace App\Http\Controllers;

use App\Models\OrganizationChart;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrganizationChartController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return Inertia::render('Organization/Index');
    }

    public function list(Request $request)
    {
        $query = OrganizationChart::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['Name', 'Position']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'Name'     => 'required|string|max:100',
            'Position' => 'required|string|max:200',
        ]);

        $member = OrganizationChart::create($validated);

        return response()->json([
            'message' => 'Organization member logged successfully!',
            'member'  => $member
        ], 201);
    }

    public function show($id)
    {
        $member = OrganizationChart::findOrFail($id);
        return response()->json($member);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $member = OrganizationChart::findOrFail($id);
        
        $validated = $request->validate([
            'Name'     => 'sometimes|string|max:100',
            'Position' => 'sometimes|string|max:200',
        ]);

        $member->update($validated);

        return response()->json([
            'message' => 'Member profile synchronized successfully.',
            'member'  => $member->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $member = OrganizationChart::findOrFail($id);
        $member->delete();

        return response()->json(['message' => 'Member removed from organizational records.'], 200);
    }
}