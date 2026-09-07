<?php

namespace App\Http\Controllers;

use App\Models\BincardIssuedRecord;
use App\Models\BincardRecord;
use App\Models\EmpAccountabilityCard;
use App\Models\EmpAccountsRecord;
use App\Models\EmpPgcRecord;
use App\Models\InventCustodianSlip;
use App\Models\InventCustodianSlipDescrp;
use App\Models\InventFurnitureFixtures;
use App\Models\InventoryDictionary;
use App\Models\OfficeDictionary;
use App\Models\OrganizationChart;
use App\Models\PropertyAccountabilityReceipt;
use App\Models\PropertyReturnSlip;
use App\Models\PrsPurposeDictionary;
use App\Models\TripTicketApprovalLog;
use App\Models\TripTicketDriver;
use App\Models\TripTicketFuelRecord;
use App\Models\TripTicketMaintenance;
use App\Models\TripTicketPassenger;
use App\Models\TripTicketRecord;
use App\Models\TripTicketVehicle;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Render the dashboard view via Inertia. The actual /dashboard route in
     * web.php currently uses an inline closure instead of this method — see
     * the note there if wiring this controller in directly.
     */
    public function index()
    {
        return inertia('Dashboard', [
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * JSON endpoint the Dashboard page fetches on mount (matches the
     * existing dashboard/stats route).
     */
    public function stats()
    {
        return response()->json($this->getStats());
    }

    /**
     * Aggregates lightweight counts across every GSO module for the
     * dashboard overview. Kept as simple count()/where() queries — no
     * heavy joins — since this runs on every dashboard page load.
     */
    private function getStats()
    {
        return [
            'trip_ticket' => [
                'pending'   => TripTicketRecord::where('status', 'Pending')->count(),
                'approved'  => TripTicketRecord::where('status', 'Approved')->count(),
                'completed' => TripTicketRecord::where('status', 'Completed')->count(),
                'total'     => TripTicketRecord::count(),
            ],
            'maintenance' => [
                'records_scheduled'       => TripTicketMaintenance::where('status', 'Scheduled')->count(),
                'records_completed'      => TripTicketMaintenance::where('status', 'Completed')->count(),
                'records_cancelled'      => TripTicketMaintenance::where('status', 'Cancelled')->count(),
                'records_total'           => TripTicketMaintenance::count(),
                // Counts distinct vehicles with an "In Progress" maintenance
                // log, rather than TripTicketVehicle.status = 'Maintenance' —
                // the maintenance record's own status is the source of truth
                // for whether work is actually underway right now.
                'vehicles_in_maintenance' => TripTicketMaintenance::where('status', 'In Progress')->distinct('vehicle_id')->count('vehicle_id'),
            ],
            'users' => [
                'total'    => User::count(),
                'linked'   => User::whereHas('employeeRecord')->count(),
                'unlinked' => User::whereDoesntHave('employeeRecord')->count(),
            ],
            // Simple total-count cards for every remaining module.
            'modules' => [
                'custodian_slip'             => InventCustodianSlip::count(),
                'custodian_slip_description' => InventCustodianSlipDescrp::count(),
                'purpose_type'               => PrsPurposeDictionary::count(),
                'bincard'                    => BincardRecord::count(),
                'bincard_issued'             => BincardIssuedRecord::count(),
                'office_dictionary'          => OfficeDictionary::count(),
                'inventory_dictionary'       => InventoryDictionary::count(),
                'furniture_fixtures'         => InventFurnitureFixtures::count(),
                'organization_chart'         => OrganizationChart::count(),
                'emp_pgc_record'             => EmpPgcRecord::count(),
                'property_receipt'           => PropertyAccountabilityReceipt::count(),
                'property_return_slip'       => PropertyReturnSlip::count(),
                'emp_accountability_card'    => EmpAccountabilityCard::count(),
                'emp_accounts_record'        => EmpAccountsRecord::count(),
                'trip_ticket_vehicle'        => TripTicketVehicle::count(),
                'trip_ticket_driver'         => TripTicketDriver::count(),
                'trip_ticket_passenger'      => TripTicketPassenger::count(),
                'trip_ticket_approval_log'   => TripTicketApprovalLog::count(),
                'trip_ticket_fuel_record'    => TripTicketFuelRecord::count(),
            ],
        ];
    }
}