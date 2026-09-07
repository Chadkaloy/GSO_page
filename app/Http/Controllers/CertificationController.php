<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Certification;
use App\Http\Requests\StoreCertificationRequest;
use App\Http\Requests\UpdateCertificationRequest;
use App\Http\Resources\CertificationResource;

class CertificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('Certification/Index');
    }

    public function list(Request $request)
    {
        $query = Certification::query();

        if ($request->has('searchtext') && !empty($request->input('searchtext'))) {
            $search = $request->input('searchtext');
            $query
                ->whereLike('name', '%'.$search.'%')
                ->orWhereLike('address', '%'.$search.'%')
                ->orWhereLike('violation', '%'.$search.'%')
                ->orWhereLike('ordinance_no', '%'.$search.'%')
                ->orWhereLike('receipt_no', '%'.$search.'%');
        }

        if ($request->has('sort_field') && $request->has('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('name', 'asc'); // Default sorting
        }

        $certifications = CertificationResource::collection(
            $query->orderBy('name', 'asc')->paginate($request->input('per_page', 5))
        );

        return $certifications;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCertificationRequest $request)
    {
        $validatedData = $request->validated();

        $certification = Certification::create($validatedData);

        return response()->json([
            'message' => 'Certification created successfully!',
            'certification' => $certification // Optionally return the created certification data
        ], 201); // 201 Created status code
    }

    /**
     * Display the specified resource.
     */
    public function show(Certification $certification)
    {
        $certification = Certification::findOrFail($certification->id);

        if (!$certification) {
            return redirect()->back()->with('error', 'Certification not found.');
        }

        return response()->json($certification);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCertificationRequest $request, Certification $certification)
    {
        $validatedData = $request->validated();
        \Log::info('UpdateCertificationRequest validated data:', $validatedData);
        $certification->update($validatedData);

        return response()->json([
            'message' => 'Certification updated successfully!',
            'certification' => $certification->fresh() // Return the fresh, updated certification data
        ], 200); // 200 OK status code for successful updates
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $certification = Certification::findOrFail($id); // Find the certification or throw a 404 error
            $certification->delete(); // Delete the certification

            return response()->json(['message' => 'Certification deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete certification.'], 500);
        }
    }
}
