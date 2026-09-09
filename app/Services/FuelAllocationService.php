<?php

namespace App\Services;

use App\Models\OfficeFuelAllocation;
use App\Models\OfficeFuelAllocationTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FuelAllocationService
{
    /**
     * Reserve (deduct) the estimated liters for a new trip against the
     * requesting office's allocation for the given year (defaults to the
     * current year). Throws a ValidationException — which Laravel renders
     * as a standard 422 JSON response, same shape as $request->validate()
     * failures — if there's no allocation row for this office/year yet, or
     * if the estimate exceeds what's remaining.
     *
     * Call this when a Trip Ticket is created, ideally inside the same DB
     * transaction as the trip's own insert so a failed trip creation can't
     * leave a dangling deduction.
     */
    public function deductForTrip(int $officeId, float $estimatedLiters, int $tripId, ?int $year = null): OfficeFuelAllocationTransaction
    {
        $year = $year ?? (int) now()->year;

        return DB::transaction(function () use ($officeId, $estimatedLiters, $tripId, $year) {
            // Lock the row so two trips being created for the same office
            // at nearly the same time can't both pass the "enough
            // remaining" check against the same stale balance.
            $allocation = OfficeFuelAllocation::where('office_id', $officeId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$allocation) {
                throw ValidationException::withMessages([
                    'liters' => ["No fuel allocation has been set up for this office for {$year}. Add one on the Office Fuel Allocation page first."],
                ]);
            }

            if ($allocation->liters_remaining < $estimatedLiters) {
                throw ValidationException::withMessages([
                    'liters' => ["This office only has {$allocation->liters_remaining}L remaining for {$year}, but {$estimatedLiters}L was requested."],
                ]);
            }

            $allocation->decrement('liters_remaining', $estimatedLiters);

            return OfficeFuelAllocationTransaction::create([
                'office_fuel_allocation_id' => $allocation->id,
                'trip_id'                   => $tripId,
                'type'                      => 'deduction',
                'liters'                    => $estimatedLiters,
                'note'                      => 'Reserved at trip creation (estimate).',
            ]);
        });
    }

    /**
     * Reconcile a trip's original estimate against its actual fuel usage.
     * Refunds the unused portion back to liters_remaining if actual is less
     * than the estimate, or deducts the extra if actual exceeds it.
     *
     * Idempotent — safe to call more than once for the same trip_id. It
     * looks up the ORIGINAL deduction transaction and checks whether a
     * refund/extra_deduction already exists for this trip before moving
     * anything, so re-saving a fuel record won't double-refund.
     *
     * Call this when actual liters used gets logged for a trip (e.g. from
     * Trip Ticket Fuel Record). Returns null if there was nothing to
     * reconcile (no original deduction found, already reconciled, or the
     * amounts matched closely enough that no adjustment is needed).
     */
    public function reconcileForTrip(int $tripId, float $actualLiters): ?OfficeFuelAllocationTransaction
    {
        return DB::transaction(function () use ($tripId, $actualLiters) {
            $deduction = OfficeFuelAllocationTransaction::where('trip_id', $tripId)
                ->where('type', 'deduction')
                ->latest('id')
                ->first();

            if (!$deduction) {
                // No estimate was ever reserved for this trip (e.g. it
                // predates this feature, or its office had no allocation
                // row at creation time) — nothing to reconcile against.
                return null;
            }

            $alreadyReconciled = OfficeFuelAllocationTransaction::where('trip_id', $tripId)
                ->whereIn('type', ['refund', 'extra_deduction'])
                ->exists();

            if ($alreadyReconciled) {
                return null;
            }

            $estimatedLiters = (float) $deduction->liters;
            $difference = $estimatedLiters - $actualLiters; // positive = refund, negative = extra deduction needed

            if (abs($difference) < 0.005) {
                return null; // estimate matched actual closely enough — nothing to move
            }

            $allocation = OfficeFuelAllocation::lockForUpdate()->find($deduction->office_fuel_allocation_id);
            if (!$allocation) {
                return null;
            }

            if ($difference > 0) {
                $allocation->increment('liters_remaining', $difference);

                return OfficeFuelAllocationTransaction::create([
                    'office_fuel_allocation_id' => $allocation->id,
                    'trip_id'                   => $tripId,
                    'type'                      => 'refund',
                    'liters'                    => $difference,
                    'note'                      => "Refunded unused portion of estimate ({$estimatedLiters}L estimated, {$actualLiters}L actually used).",
                ]);
            }

            $extra = abs($difference);
            $allocation->decrement('liters_remaining', $extra);

            return OfficeFuelAllocationTransaction::create([
                'office_fuel_allocation_id' => $allocation->id,
                'trip_id'                   => $tripId,
                'type'                      => 'extra_deduction',
                'liters'                    => $extra,
                'note'                      => "Actual usage ({$actualLiters}L) exceeded the original estimate ({$estimatedLiters}L).",
            ]);
        });
    }
}
