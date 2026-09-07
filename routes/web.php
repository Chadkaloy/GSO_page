<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\PropertyReturnSlipController;
use App\Http\Controllers\EmpAccountsRecordController;
use App\Http\Controllers\EmpPgcRecordController;
use App\Http\Controllers\OfficeDictionaryController;
use App\Http\Controllers\InventoryDictionaryController;
use App\Http\Controllers\BincardRecordController;
use App\Http\Controllers\BincardIssuedRecordController;
use App\Http\Controllers\EmpAccountabilityCardController;
use App\Http\Controllers\PropertyAccountabilityReceiptController;
use App\Http\Controllers\InventCustodianSlipController;
use App\Http\Controllers\InventCustodianSlipDescrpController;
use App\Http\Controllers\InventFurnitureFixturesController;
use App\Http\Controllers\OrganizationChartController;
use App\Http\Controllers\TripTicketVehicleController;
use App\Http\Controllers\TripTicketDriverController;
use App\Http\Controllers\TripTicketController;
use App\Http\Controllers\TripTicketPassengerController;
use App\Http\Controllers\TripTicketApprovalController;
use App\Http\Controllers\TripTicketFuelRecordController;
use App\Http\Controllers\TripTicketMaintenanceController;
use App\Http\Controllers\PrsPurposeDictionaryController;
use App\Http\Controllers\LocationController;

/*
|--------------------------------------------------------------------------
| Public / Welcome Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated Core & Dashboard Base Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:S,A,E'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
});

/*
|--------------------------------------------------------------------------
| Role-Protected Administrative Workspace Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:super_admin,inventory_user'])->group(function () {
    Route::post('certifications/list', [CertificationController::class, 'list'])->name('certifications.list');
    Route::resource('certifications', CertificationController::class)->except(['create', 'edit']);
});

/*
|--------------------------------------------------------------------------
| Main Inventory & Logistics Management Modules (Unified RESTful Matrix)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:S,A,E'])->group(function () {
    // Shared location-autocomplete suggestion endpoint for Trip Ticket
    // Destination, Employee Account Address, and Property Return Slip
    // LGU Name inputs. Read-only lookup, same as other */lookup routes.
    Route::get('/locations/search', [LocationController::class, 'search'])->name('locations.search');

    // Purpose Catalog Management
    Route::post('/PrsPurposeDictionary/list', [PrsPurposeDictionaryController::class, 'list'])->name('PrsPurposeDictionary.list');
    // Must stay above the resource route below, same reasoning as other lookups.
    Route::get('/PrsPurposeDictionary/lookup', [PrsPurposeDictionaryController::class, 'lookupList'])->name('PrsPurposeDictionary.lookup');
    Route::resource('/PrsPurposeDictionary', PrsPurposeDictionaryController::class)->except(['create', 'edit']);

    // Personnel Registry Trackers
    Route::post('/Employee/list', [EmpAccountsRecordController::class, 'list'])->name('Employee.list');
    // Must stay above the resource route below, same reasoning as other lookups.
    Route::get('/Employee/lookup', [EmpAccountsRecordController::class, 'lookupList'])->name('Employee.lookup');
    Route::get('/Employee/available-users', [EmpAccountsRecordController::class, 'availableUsers'])->name('Employee.available-users');
    Route::get('/Employee/users/{id}', [EmpAccountsRecordController::class, 'showUser'])->name('Employee.show-user');
    Route::resource('/Employee', EmpAccountsRecordController::class)->except(['create', 'edit']);

    Route::post('/Pgc/list', [EmpPgcRecordController::class, 'list'])->name('Pgc.list');
    Route::resource('/Pgc', EmpPgcRecordController::class)->except(['create', 'edit']);

    // Office Infrastructure Mappings
    Route::post('/Office/list', [OfficeDictionaryController::class, 'list'])->name('Office.list');
    Route::resource('/Office', OfficeDictionaryController::class)->except(['create', 'edit']);

    // Main Asset & Card Dictionaries
    Route::post('/Inventory/list', [InventoryDictionaryController::class, 'list'])->name('Inventory.list');
    // Must stay above the resource route below, same reasoning as other lookups.
    Route::get('/Inventory/lookup', [InventoryDictionaryController::class, 'lookupList'])->name('Inventory.lookup');
    Route::get('/Inventory/lookup-by-code/{code}', [InventoryDictionaryController::class, 'lookupByCode'])->name('Inventory.lookup-by-code');
    Route::resource('/Inventory', InventoryDictionaryController::class)->except(['create', 'edit']);

    Route::post('/Bincard/list', [BincardRecordController::class, 'list'])->name('Bincard.list');
    // Must stay above the resource route below, same reasoning as other lookups.
    Route::get('/Bincard/lookup', [BincardRecordController::class, 'lookupList'])->name('Bincard.lookup');
    Route::resource('/Bincard', BincardRecordController::class)->except(['create', 'edit']);

    // Bincard Issued Record Module (Updated with both GET and POST for list endpoint requests)
    Route::get('/BincardIssued/list', [BincardIssuedRecordController::class, 'list'])->name('BincardIssued.list.get');
    Route::post('/BincardIssued/list', [BincardIssuedRecordController::class, 'list'])->name('BincardIssued.list');
    Route::resource('/BincardIssued', BincardIssuedRecordController::class)->except(['create', 'edit']);

    Route::post('/Accountability/list', [EmpAccountabilityCardController::class, 'list'])->name('Accountability.list');
    Route::resource('/Accountability', EmpAccountabilityCardController::class)->except(['create', 'edit']);

    // Property Receipts & Return Pipeline 
    Route::post('/PropertyReceipt/list', [PropertyAccountabilityReceiptController::class, 'list'])->name('PropertyReceipt.list');
    Route::resource('/PropertyReceipt', PropertyAccountabilityReceiptController::class)->except(['create', 'edit']);

    Route::post('/PropertyReturnSlip/list', [PropertyReturnSlipController::class, 'list'])->name('PropertyReturnSlip.list');
    Route::resource('/PropertyReturnSlip', PropertyReturnSlipController::class)->except(['create', 'edit']);

    // Inventory Custodian Slips Workspace
    Route::post('/CustodianSlip/list', [InventCustodianSlipController::class, 'list'])->name('CustodianSlip.list');
    // Must stay above the resource route below, same reasoning as other lookups.
    Route::get('/CustodianSlip/lookup', [InventCustodianSlipController::class, 'lookupList'])->name('CustodianSlip.lookup');
    Route::resource('/CustodianSlip', InventCustodianSlipController::class)->except(['create', 'edit']);
     
    Route::post('/CustodianSlipDescrp/list', [InventCustodianSlipDescrpController::class, 'list'])->name('CustodianSlipDescrp.list');
    Route::resource('/CustodianSlipDescrp', InventCustodianSlipDescrpController::class)->except(['create', 'edit']);  

    // Furniture & Fixtures Upgraded RESTful Ledger Module
    Route::post('/FurnitureFixtures/list', [InventFurnitureFixturesController::class, 'list'])->name('FurnitureFixtures.list');
    Route::resource('/FurnitureFixtures', InventFurnitureFixturesController::class)->except(['create', 'edit']);

    // System Organizational Visualizers
    Route::post('/Organization/list', [OrganizationChartController::class, 'list'])->name('Organization.list');
    Route::resource('/Organization', OrganizationChartController::class)->except(['create', 'edit']);

    // Fleet & Trip Logistical Tracking Ecosystem 
    Route::post('/TripTicketVehicle/list', [TripTicketVehicleController::class, 'list'])->name('TripTicketVehicle.list');
    // Must stay above the resource route below, same reasoning as TripTicket/pending.
    Route::get('/TripTicketVehicle/available', [TripTicketVehicleController::class, 'getAvailableVehicles'])->name('TripTicketVehicle.available');
    Route::get('/TripTicketVehicle/lookup', [TripTicketVehicleController::class, 'lookupList'])->name('TripTicketVehicle.lookup');
    Route::resource('/TripTicketVehicle', TripTicketVehicleController::class)->except(['create', 'edit']);

    Route::post('/TripTicketDriver/list', [TripTicketDriverController::class, 'list'])->name('TripTicketDriver.list');
    // Must stay above the resource route below, same reasoning as TripTicket/pending.
    Route::get('/TripTicketDriver/available', [TripTicketDriverController::class, 'getAvailableDrivers'])->name('TripTicketDriver.available');
    Route::resource('/TripTicketDriver', TripTicketDriverController::class)->except(['create', 'edit']);

    // Full Matrix RESTful Endpoints mapping for TripTicket Actions
    Route::get('/TripTicket', [TripTicketController::class, 'index'])->name('TripTicket.index');
    Route::post('/TripTicket/list', [TripTicketController::class, 'list'])->name('TripTicket.list');
    // Must stay above the resource route below: Route::resource registers
    // GET /TripTicket/{tripTicket} for show(), which would otherwise treat
    // "pending" as the {tripTicket} id and never reach pendingList().
    Route::get('/TripTicket/pending', [TripTicketController::class, 'pendingList'])->name('TripTicket.pending');
    Route::get('/TripTicket/approved', [TripTicketController::class, 'approvedList'])->name('TripTicket.approved');
    Route::resource('/TripTicket', TripTicketController::class)->except(['create', 'edit', 'index']);

    // Full Matrix RESTful Endpoints mapping for Passenger Manifest Management
    Route::get('/TripTicketPassenger', [TripTicketPassengerController::class, 'index'])->name('TripTicketPassenger.index');
    Route::post('/TripTicketPassenger/list', [TripTicketPassengerController::class, 'list'])->name('TripTicketPassenger.list');
    // Must stay above the resource route below: Route::resource registers
    // POST /TripTicketPassenger for store(), and would otherwise conflict
    // with/shadow this bulk-create endpoint if declared after it.
    Route::post('/TripTicketPassenger/bulk', [TripTicketPassengerController::class, 'storeBulk'])->name('TripTicketPassenger.bulk');
    Route::post('/TripTicketPassenger/grouped', [TripTicketPassengerController::class, 'listGrouped'])->name('TripTicketPassenger.grouped');
    Route::resource('/TripTicketPassenger', TripTicketPassengerController::class)->except(['create', 'edit', 'index']);

    // Full Matrix RESTful Endpoints mapping for Approval Logs Tracking
    Route::get('/TripTicketApproval', [TripTicketApprovalController::class, 'index'])->name('TripTicketApproval.index');
    Route::post('/TripTicketApproval/list', [TripTicketApprovalController::class, 'list'])->name('TripTicketApproval.list');
    Route::resource('/TripTicketApproval', TripTicketApprovalController::class)->except(['create', 'edit', 'index']);

    Route::get('/TripTicketFuel', [TripTicketFuelRecordController::class, 'index'])->name('TripTicketFuel.index');
    Route::post('/TripTicketFuel/list', [TripTicketFuelRecordController::class, 'list'])->name('TripTicketFuel.list');
    Route::resource('/TripTicketFuel', TripTicketFuelRecordController::class)->except(['create', 'edit', 'index']);

    Route::get('/TripTicketMaintenance', [TripTicketMaintenanceController::class, 'index'])->name('TripTicketMaintenance.index');
    Route::post('/TripTicketMaintenance/list', [TripTicketMaintenanceController::class, 'list'])->name('TripTicketMaintenance.list');
    Route::resource('/TripTicketMaintenance', TripTicketMaintenanceController::class)->except(['create', 'edit', 'index']);
});

/*
|--------------------------------------------------------------------------
| Internal Sub-System Structural Inclusions
|--------------------------------------------------------------------------
*/
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';