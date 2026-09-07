<?php

namespace App\Http\Controllers;

use App\Models\BincardRecord;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BincardRecordController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        // FIXED: Changed from 'Inventory/Bincard/Index' to match your actual folder structure
        return Inertia::render('Bincard/Index');
    }

    public function list(Request $request)
    {
        $query = BincardRecord::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['Supplier', 'Descrp', 'PoNo']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('bin_Date', 'desc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Lightweight, search-aware lookup for the Bin ID combobox on the
     * Bincard Issued Record form. Reuses applyRelevanceSearch() so results
     * are filtered AND ranked by closeness the same way the main list()
     * search already works — the closest match rises to the top of the
     * suggestions instead of just returning matches in arbitrary order.
     */
    public function lookupList(Request $request)
    {
        $query = BincardRecord::query();

        if ($request->filled('search')) {
            $this->applyRelevanceSearch($query, $request->input('search'), ['Supplier', 'Descrp', 'PoNo']);
        } else {
            $query->orderBy('bin_Date', 'desc');
        }

        return response()->json(
            $query->limit(20)->get(['id', 'Descrp', 'Supplier', 'PoNo', 'Balance'])
        );
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'bin_Date' => 'required|date',
            'Supplier' => 'required|string|max:100',
            'Descrp' => 'required|string|max:250',
            'Qty' => 'required|string|max:10',
            'Issued' => 'required|integer',
            'Balance' => 'required|numeric',
            'PoNo' => 'required|string|max:50',
        ]);

        $bincard = BincardRecord::create($validated);

        return response()->json([
            'message' => 'Bincard record created successfully!',
            'bincard' => $bincard
        ], 201);
    }

    public function show($id)
    {
        $bincard = BincardRecord::findOrFail($id);
        return response()->json($bincard);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $bincard = BincardRecord::findOrFail($id);
        $validated = $request->validate([
            'bin_Date' => 'sometimes|date',
            'Supplier' => 'sometimes|string|max:100',
            'Descrp' => 'sometimes|string|max:250',
            'Qty' => 'sometimes|string|max:10',
            'Issued' => 'sometimes|integer',
            'Balance' => 'sometimes|numeric',
            'PoNo' => 'sometimes|string|max:50',
        ]);

        $bincard->update($validated);

        return response()->json([
            'message' => 'Bincard record updated successfully!',
            'bincard' => $bincard->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $bincard = BincardRecord::findOrFail($id);
        $bincard->delete();

        return response()->json(['message' => 'Bincard record deleted successfully.'], 200);
    }
}