<?php

namespace App\Http\Controllers;

use App\Models\TripTicketSignatorySetting;
use Illuminate\Http\Request;

class TripTicketSignatorySettingController extends Controller
{
    public function index()
    {
        return inertia('TripTicketSignatorySetting/Index');
    }

    /**
     * Returns the single settings row, creating it with null values if it
     * somehow doesn't exist yet (defensive — the migration already seeds it).
     */
    public function show()
    {
        $settings = TripTicketSignatorySetting::firstOrCreate(['id' => 1]);

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'municipal_administrator_name' => 'nullable|string|max:150',
            'municipal_mayor_name'         => 'nullable|string|max:150',
        ]);

        $settings = TripTicketSignatorySetting::firstOrCreate(['id' => 1]);
        $settings->update($validated);

        return response()->json([
            'message'  => 'Signatory settings updated successfully!',
            'settings' => $settings->fresh(),
        ]);
    }
}
