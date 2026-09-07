<script setup lang="ts">
/**
 * @file TripTicketFuel Index Component (`Index.vue`)
 * @description Vue 3 Single File Component (SFC) designed for tracking fuel transaction registries,
 *              featuring TanStack tables, Zod-backed validation, Axios CRUD operations, 
 *              and modal-based form entry management.
 */

/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

/* Import Utilities */
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { ArrowUpDown, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table';
import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/TripTicketFuel';
const baseentityname = 'Fuel Record';

/**
 * Breadcrumbs configuration mapping layout directory hierarchies.
 */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Trip Ticket Fuel Records',
        href: baseentityurl,
    },
];

/**
 * TypeScript interface aligning with backend database schema columns for fuel records.
 */
export interface TripTicketFuelRecord {
    id: number;
    trip_id: number;
    // Eager-loaded from the trip relation (backend list()/show() both load this).
    trip?: { id: number; trip_no: string } | null;
    fuel_liters: number;
    fuel_cost: number;
    odometer_start: number | null;
    odometer_end: number | null;
    fuel_date: string;
    station_name: string;
    or_number: string | null;
    recorded_by: number;
    // Eager-loaded from the recorder relation — automatically the logged-in
    // user's employee record, never manually chosen.
    recorder?: { accID: number; fullName: string } | null;
    remarks: string | null;
}

/**
 * Action permission flags shared globally via Inertia (see
 * AppServiceProvider::boot()) — derived from the logged-in user's accLevel.
 */
const permissions = computed(() => usePage().props.permissions as {
    create: boolean;
    edit: boolean;
    delete: boolean;
    view: boolean;
    print: boolean;
});

/**
 * TanStack table column configuration definitions and styling hooks.
 */
const columns: ColumnDef<TripTicketFuelRecord>[] = [
    {
        id: 'select',
        header: ({ table }) =>
            h(Checkbox, {
                modelValue:
                    table.getIsAllPageRowsSelected() ||
                    (table.getIsSomePageRowsSelected() && 'indeterminate'),
                'onUpdate:modelValue': (value) =>
                    table.toggleAllPageRowsSelected(!!value),
                ariaLabel: 'Select all',
            }),
        cell: ({ row }) =>
            h(Checkbox, {
                modelValue: row.getIsSelected(),
                'onUpdate:modelValue': (value) => row.toggleSelected(!!value),
                ariaLabel: 'Select row',
            }),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: 'or_number',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['OR Number', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'font-mono font-bold text-slate-900 dark:text-slate-100' }, row.getValue('or_number') || '—'),
    },
    {
        accessorKey: 'station_name',
        header: 'Gas Station',
        cell: ({ row }) => h('div', { class: 'truncate max-w-[150px]' }, row.getValue('station_name')),
    },
    {
        accessorKey: 'fuel_liters',
        header: 'Liters',
        cell: ({ row }) => h('div', {}, `${parseFloat(row.getValue('fuel_liters')).toFixed(2)} L`),
    },
    {
        accessorKey: 'fuel_cost',
        header: 'Cost',
        cell: ({ row }) => h('div', { class: 'font-semibold text-emerald-600' }, `₱${parseFloat(row.getValue('fuel_cost')).toFixed(2)}`),
    },
    {
        accessorKey: 'fuel_date',
        header: 'Refuel Date',
        cell: ({ row }) => h('div', {}, row.getValue('fuel_date')),
    },
    {
        id: 'trip_no',
        accessorFn: (row) => row.trip?.trip_no ?? null,
        header: 'Trip No.',
        cell: ({ row }) => h('div', { class: 'font-mono text-xs' }, row.original.trip?.trip_no ?? '—'),
    },
    {
        id: 'recorder_name',
        accessorFn: (row) => row.recorder?.fullName ?? null,
        header: 'Logged By',
        cell: ({ row }) => h('div', { class: 'text-xs' }, row.original.recorder?.fullName ?? '—'),
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const rowitem = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(ReusableDropDownAction, {
                    rowitem,
                    onEdit: permissions.value.edit ? handleEdit : undefined,
                    onDelete: permissions.value.delete ? openDeleteDialog : undefined,
                }),
            );
        },
    },
];

/* Dialog and UI State Control References */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);

// Options for the "Trip" dropdown — only Approved trips, per the request that
// fuel purchases should be logged against trips that came out of the
// Approval workflow, not any arbitrary trip. Reuses the same
// GET /TripTicket/approved endpoint built for the Approval page's
// "Completed" decision.
const approvedTripOptions = ref<{ id: number; trip_no: string }[]>([]);
const loadingApprovedTrips = ref(false);

const fetchApprovedTripOptions = async () => {
    loadingApprovedTrips.value = true;
    try {
        const response = await axios.get('/TripTicket/approved');
        approvedTripOptions.value = response.data;
    } catch (error) {
        toast.error('Failed to load approved trip list.');
    } finally {
        loadingApprovedTrips.value = false;
    }
};

// When editing a fuel record whose trip has since moved past Approved (e.g.
// it's now Completed), that trip won't be in the Approved-only list. Fetch
// it specifically and splice it in so the dropdown still shows its trip_no.
const ensureTripInOptions = async (tripId: number) => {
    if (!tripId || approvedTripOptions.value.some((t) => t.id === tripId)) return;
    try {
        const response = await axios.get(`/TripTicket/${tripId}`);
        approvedTripOptions.value = [
            { id: response.data.id, trip_no: response.data.trip_no },
            ...approvedTripOptions.value,
        ];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this trip.
    }
};

/**
 * Zod validation schema ensuring data types correspond safely to database standards.
 */
const schema = z.object({
    trip_id: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Trip ID reference is required' }).int().positive()),
    fuel_liters: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Fuel liters volume is required' }).positive()),
    fuel_cost: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Total fueling cost is required' }).positive()),
    odometer_start: z.preprocess((val) => (val === '' || val === null || val === undefined ? undefined : Number(val)), z.number().positive().optional()),
    odometer_end: z.preprocess((val) => (val === '' || val === null || val === undefined ? undefined : Number(val)), z.number().positive().optional()),
    fuel_date: z.string({ required_error: 'Refuel execution date is required' }),
    station_name: z.string({ required_error: 'Gas station brand name is required' }).max(100),
    or_number: z.string().max(50).nullable().optional(),
    remarks: z.string().nullable().optional(),
});

/**
 * Configuration mapping for AutoForm field attributes, labels, and placeholders.
 */
const fieldconfig: any = {
    fuel_liters: { label: 'Fuel Volume (Liters)', inputProps: { type: 'number', step: '0.01', placeholder: '0.00' } },
    fuel_cost: { label: 'Total Transaction Cost (₱)', inputProps: { type: 'number', step: '0.01', placeholder: '0.00' } },
    odometer_start: { label: 'Odometer Starting Reading', inputProps: { type: 'number', placeholder: 'Optional start km metric' } },
    odometer_end: { label: 'Odometer Ending Reading', inputProps: { type: 'number', placeholder: 'Optional final km metric' } },
    fuel_date: { label: 'Purchase Date', inputProps: { type: 'date' } },
    station_name: { label: 'Gas Station Name', inputProps: { type: 'text', placeholder: 'e.g., Shell Maramag' } },
    or_number: { label: 'Official Receipt Number (OR #)', inputProps: { type: 'text', placeholder: 'Invoice serial token' } },
    remarks: { label: 'Log Execution Remarks', inputProps: { type: 'textarea', placeholder: 'Add logging details or purchase abnormalities here...' } },
};

/**
 * Vee-Validate form initialization hook with typing schema and default defaults.
 */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        trip_id: '' as any,
        fuel_liters: '' as any,
        fuel_cost: '' as any,
        odometer_start: '' as any,
        odometer_end: '' as any,
        fuel_date: new Date().toISOString().slice(0, 10),
        station_name: '',
        or_number: '',
        remarks: '',
    },
});

/**
 * Resets form states and clears tracked item identifiers.
 */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

/**
 * Opens the dialog form under 'create' mode specifications.
 */
const handleOpenDialogForm = () => {
    resetForm();
    mode.value = 'create';
    showDialogForm.value = true;
    fetchApprovedTripOptions();
};

/**
 * Handles form data submission for adding or modifying fuel record data via Axios.
 */
const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(baseentityurl, values);
            toast.success('Fuel transaction ledger entry committed.');
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success('Fuel record tracking values updated.');
        }

        resetForm();
        await tableRef.value?.fetchRows();
        showDialogForm.value = false;
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Validation conflict. Review the input limits.');
        } else {
            toast.error('Database logging tracking service failure.');
        }
    }
};

/**
 * Fetches target fuel log details by ID to populate the edit form dialog.
 */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await fetchApprovedTripOptions();

        const response = await axios.get(`${baseentityurl}/${id}`);
        const data = response.data;

        await ensureTripInOptions(data.trip_id);

        form.setValues({
            trip_id: data.trip_id,
            fuel_liters: data.fuel_liters,
            fuel_cost: data.fuel_cost,
            odometer_start: data.odometer_start ?? '',
            odometer_end: data.odometer_end ?? '',
            fuel_date: data.fuel_date,
            station_name: data.station_name,
            or_number: data.or_number ?? '',
            remarks: data.remarks ?? '',
        });
        showDialogForm.value = true;
    } catch (error) {
        toast.error('Could not load specific data payload indices.');
    }
};

/* Deletion Dialog States */
const showDeleteDialog = ref(false);
const itemIDToDelete = ref<number | null>(null);

/**
 * Opens the deletion confirmation dialog for a specific fuel record ID.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id;
    showDeleteDialog.value = true;
};

/**
 * Sends a DELETE request to clear the specified log entry and refreshes the data table.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;
        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`);
        toast.success('Log trace completely cleared.');
        await tableRef.value?.fetchRows();
        showDeleteDialog.value = false;
    } catch (error) {
        toast.error('Could not execute record index purge.');
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            
            <!-- Toolbar action section -->
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600 hover:bg-blue-700 text-white" @click="handleOpenDialogForm">
                        <Plus class="h-4 w-4 mr-1" /> Log Refueling Event
                    </Button>
                </div>
            </div>

            <!-- Table component properly mapped with ref="tableRef" for auto-refresh -->
            <ReusableDataTable
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <!-- Create / Update modal form dialog component -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[475px] max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} Fuel Transaction Log</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Commit audit data parameters verifying official agency service vehicle fuel purchasing events.
                    </DialogDescription>
                    
                    <AutoForm
                        class="space-y-4 pt-2"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
                        <template #trip_id>
                            <FormField v-slot="{ componentField }" name="trip_id">
                                <FormItem>
                                    <FormLabel>Trip (Approved)</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingApprovedTrips ? 'Loading trips…' : 'Select an approved trip'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="trip in approvedTripOptions"
                                                :key="trip.id"
                                                :value="String(trip.id)"
                                            >
                                                {{ trip.trip_no }}
                                            </SelectItem>
                                            <div v-if="!loadingApprovedTrips && approvedTripOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No approved trips found.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <DialogFooter class="pt-4 gap-2">
                            <Button type="button" variant="outline" @click="showDialogForm = false">Cancel</Button>
                            <Button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white">
                                {{ mode === 'create' ? 'Commit Entry' : 'Save Modifications' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Decommissioning confirmation alert dialog component -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Purge Fuel Receipt Audit Record?`"
                :description="`Are you certain you want to drop this transaction record? Discarding this ledger entry will shift balance totals and drop historical expense entries.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>