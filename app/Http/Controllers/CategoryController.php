<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Mail\DataCreatedNotification;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('Category/Index');
    }

    /**
     * Store a newly created resource in storage.
     */

     public function list(Request $request)
     {
        \Log::info("Entered list function");
        $query = Category::query();

        \Log::info($request);

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $search = $request->input('searchtext');
            $query
                ->whereLike('name', '%'.$search.'%')
                ->orWhereLike('description', '%'.$search.'%');
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('name', 'asc'); // Default sorting
        }

        $categories = CategoryResource::collection(
            $query->orderBy('name', 'asc')->paginate($request->input('per_page', 5))
        );

        \Log::info('categories:',['categories' => $categories]);
        return $categories;
     }

    public function store(StoreCategoryRequest $request)
    {
        // \Log::info("message", ['request' => $request->all()]);

        $validatedData = $request->validated();

        $category = Category::create($validatedData);

        // Send email notification
        \Mail::to('shendilzer18@gmail.com')->send(new DataCreatedNotification($category->id,$category, url('/categories/'.$category->id)));

        return response()->json([
            'message' => 'Category created successfully!',
            'category' => $category // Optionally return the created category data
        ], 201); // 201 Created status code
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        // \Log::info("message", ['category' => $category->id]);
        // echo $category;

        $category = Category::findOrFail($category->id);

        if (!$category) {
            return redirect()->back()->with('error', 'Category not found.');
        }

        return response()->json($category);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validatedData = $request->validated();

        $category->update($validatedData);

        return response()->json([
            'message' => 'Category updated successfully!',
            'category' => $category->fresh() // Return the fresh, updated category data
        ], 200); // 200 OK status code for successful updates
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id); // Find the category or throw a 404 error
            $category->delete(); // Delete the category

            return response()->json(['message' => 'Category deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete category.'], 500);
        }
    }
}
