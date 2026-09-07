<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

/**
 * Action permission flags shared globally via Inertia (see
 * AppServiceProvider::boot()) — derived from the logged-in user's accLevel.
 * The dashboard itself is read-only for every role, but kept here in case
 * future widgets (e.g. quick-create shortcuts) need to check it.
 */
const permissions = computed(() => usePage().props.permissions as {
    create: boolean;
    edit: boolean;
    delete: boolean;
    view: boolean;
    print: boolean;
});

interface DashboardStats {
    trip_ticket: {
        pending: number;
        approved: number;
        completed: number;
        total: number;
    };
    maintenance: {
        records_scheduled: number;
        records_completed: number;
        records_cancelled: number;
        records_total: number;
        vehicles_in_maintenance: number;
    };
    users: {
        total: number;
        linked: number;
        unlinked: number;
    };
    modules: {
        custodian_slip: number;
        custodian_slip_description: number;
        purpose_type: number;
        bincard: number;
        bincard_issued: number;
        office_dictionary: number;
        inventory_dictionary: number;
        furniture_fixtures: number;
        organization_chart: number;
        emp_pgc_record: number;
        property_receipt: number;
        property_return_slip: number;
        emp_accountability_card: number;
        emp_accounts_record: number;
        trip_ticket_vehicle: number;
        trip_ticket_driver: number;
        trip_ticket_passenger: number;
        trip_ticket_approval_log: number;
        trip_ticket_fuel_record: number;
    };
}

const stats = ref<DashboardStats | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const fetchStats = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await axios.get('/dashboard/stats');
        stats.value = response.data;
    } catch (err) {
        console.error('Failed to load dashboard stats:', err);
        error.value = 'Could not load dashboard statistics. Try refreshing the page.';
    } finally {
        loading.value = false;
    }
};

onMounted(fetchStats);

/**
 * Display labels + routes for the simple total-count module cards.
 * Keeping this as one array (rather than repeating markup 18 times) makes
 * it a one-line change to add/remove/relabel a module card later.
 */
const moduleCardConfig: { key: keyof DashboardStats['modules']; label: string; href: string }[] = [
    { key: 'custodian_slip', label: 'Custodian Slips', href: '/CustodianSlip' },
    { key: 'custodian_slip_description', label: 'Custodian Slip Descriptions', href: '/CustodianSlipDescrp' },
    { key: 'purpose_type', label: 'Purpose Types', href: '/PrsPurposeDictionary' },
    { key: 'bincard', label: 'Bincard Records', href: '/Bincard' },
    { key: 'bincard_issued', label: 'Bincard Issued Records', href: '/BincardIssued' },
    { key: 'office_dictionary', label: 'Offices', href: '/Office' },
    { key: 'inventory_dictionary', label: 'Inventory Items', href: '/Inventory' },
    { key: 'furniture_fixtures', label: 'Furniture & Fixtures', href: '/FurnitureFixtures' },
    { key: 'organization_chart', label: 'Organization Chart Members', href: '/Organization' },
    { key: 'emp_pgc_record', label: 'PGC Records', href: '/Pgc' },
    { key: 'property_receipt', label: 'Property Accountability Receipts', href: '/PropertyReceipt' },
    { key: 'property_return_slip', label: 'Property Return Slips', href: '/PropertyReturnSlip' },
    { key: 'emp_accountability_card', label: 'Employee Accountability Cards', href: '/Accountability' },
    { key: 'emp_accounts_record', label: 'Employee Accounts', href: '/Employee' },
    { key: 'trip_ticket_vehicle', label: 'Trip Ticket Vehicles', href: '/TripTicketVehicle' },
    { key: 'trip_ticket_driver', label: 'Trip Ticket Drivers', href: '/TripTicketDriver' },
    { key: 'trip_ticket_passenger', label: 'Trip Ticket Passengers', href: '/TripTicketPassenger' },
    { key: 'trip_ticket_approval_log', label: 'Trip Ticket Approval Logs', href: '/TripTicketApproval' },
    { key: 'trip_ticket_fuel_record', label: 'Trip Ticket Fuel Records', href: '/TripTicketFuel' },
];
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <div v-if="error" class="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                {{ error }}
            </div>

            <!-- Trip Ticket overview -->
            <section>
                <h2 class="mb-2 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Trip Ticket Overview</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Pending</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats?.trip_ticket.pending ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Approved</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats?.trip_ticket.approved ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Completed</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats?.trip_ticket.completed ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Total Trips</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold">{{ stats?.trip_ticket.total ?? 0 }}</div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Maintenance overview -->
            <section>
                <h2 class="mb-2 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Maintenance Overview</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Scheduled</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats?.maintenance.records_scheduled ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Completed</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats?.maintenance.records_completed ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Cancelled</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-muted-foreground">{{ stats?.maintenance.records_cancelled ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Maintenance Records Total</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold">{{ stats?.maintenance.records_total ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Vehicles In Maintenance</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats?.maintenance.vehicles_in_maintenance ?? 0 }}</div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Users overview -->
            <section>
                <h2 class="mb-2 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Users</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Total Registered</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold">{{ stats?.users.total ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Roles Assigned</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats?.users.linked ?? 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">Roles Not Assigned</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <div v-else class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats?.users.unlinked ?? 0 }}</div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- All other modules: simple total-count cards -->
            <section>
                <h2 class="mb-2 text-sm font-semibold text-muted-foreground uppercase tracking-wide">All Modules</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <a v-for="module in moduleCardConfig" :key="module.key" :href="module.href" class="block">
                        <Card class="transition-colors hover:bg-muted/50">
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium text-muted-foreground">{{ module.label }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Skeleton v-if="loading" class="h-8 w-12" />
                                <div v-else class="text-2xl font-bold">{{ stats?.modules[module.key] ?? 0 }}</div>
                            </CardContent>
                        </Card>
                    </a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>