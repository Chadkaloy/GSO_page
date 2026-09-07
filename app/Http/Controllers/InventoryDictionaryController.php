<?php

namespace App\Http\Controllers;

use App\Models\InventoryDictionary;
use App\Traits\HasRelevanceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InventoryDictionaryController extends Controller
{
    use HasRelevanceSearch;
    public function index()
    {
        return inertia('Inventory/Index');
    }

    public function list(Request $request)
    {
        $query = InventoryDictionary::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $this->applyRelevanceSearch($query, $request->input('searchtext'), ['AC_COA_Cir_04-08', 'AC_COA_Cir_015-09', 'AC_Name(Old)', 'AC_name(New)']);
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('AC_name(New)', 'asc');
        }

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Lightweight lookup list for parent-selection dropdowns elsewhere in
     * the system (e.g. the Employee Accountability Card form's Item Code
     * field) — every inventory item shown by its code + name.
     */
    public function lookupList()
    {
        return response()->json(
            InventoryDictionary::orderBy('AC_name(New)')
                ->get(['AC_COA_Cir_04-08', 'AC_Name(Old)', 'AC_name(New)'])
        );
    }

    /**
     * Fetches a single inventory item by its code (not its numeric primary
     * key) — used when editing a record whose itemCode isn't present in the
     * lookupList() results for whatever reason, so the dropdown can still
     * show a label for it instead of going blank.
     */
    public function lookupByCode($code)
    {
        $item = InventoryDictionary::where('AC_COA_Cir_04-08', $code)
            ->firstOrFail(['AC_COA_Cir_04-08', 'AC_Name(Old)', 'AC_name(New)']);

        return response()->json($item);
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $validated = $request->validate([
            'AC_COA_Cir_04-08' => 'required|string|max:25|unique:inventory_dictionary,AC_COA_Cir_04-08',
            'AC_COA_Cir_015-09' => 'required|string|max:25',
            'AC_Name(Old)' => 'required|string|max:100',
            'AC_name(New)' => 'required|string|max:100',
        ]);

        $inventory = InventoryDictionary::create($validated);

        return response()->json([
            'message' => 'Inventory item created successfully!',
            'inventory' => $inventory
        ], 201);
    }

    public function show($id)
    {
        $inventory = InventoryDictionary::findOrFail($id);
        return response()->json($inventory);
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('edit');

        $inventory = InventoryDictionary::findOrFail($id);

        $validated = $request->validate([
            'AC_COA_Cir_04-08' => [
                'sometimes',
                'string',
                'max:25',
                Rule::unique('inventory_dictionary', 'AC_COA_Cir_04-08')->ignore($id, 'Invent_ID')
            ],
            'AC_COA_Cir_015-09' => 'sometimes|string|max:25',
            'AC_Name(Old)' => 'sometimes|string|max:100',
            'AC_name(New)' => 'sometimes|string|max:100',
        ]);

        $inventory->update($validated);

        return response()->json([
            'message' => 'Inventory item updated successfully!',
            'inventory' => $inventory->fresh()
        ]);
    }

    public function destroy($id)
    {
        Gate::authorize('delete');

        $inventory = InventoryDictionary::findOrFail($id);
        $inventory->delete();

        return response()->json(['message' => 'Inventory item deleted successfully.'], 200);
    }
}