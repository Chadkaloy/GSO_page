<script setup lang="ts">
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
import { Head, router, usePage } from '@inertiajs/vue3';

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
const baseentityurl = '/TripTicketMaintenance';
const baseentityname = 'Maintenance Record';

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Trip Ticket Maintenance Logs',
        href: baseentityurl,
    },
];

/* TypeScript Interface matching database columns exactly */
export interface TripTicketMaintenance {
    id: number;
    vehicle_id: number;
    // Eager-loaded from the vehicle relation (id + plate_no only).
    vehicle?: { id: number; plate_no: string } | null;
    maintenance_date: string;
    maintenance_type: string;
    description: string;
    cost: number;
    service_provider: string;
    status: 'Scheduled' | 'In Progress' | 'Cancelled' | 'Completed';
    next_maintenance_date: string | null;
    remarks: string | null;
    recorded_by: number | null;
    // Eager-loaded from the recorder relation — automatically the logged-in
    // user's employee record, never manually chosen.
    recorder?: { accID: number; fullName: string } | null;
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

/* Define Table Columns */
const columns: ColumnDef<TripTicketMaintenance>[] = [
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
        accessorKey: 'maintenance_type',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Type', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'font-semibold text-slate-900 dark:text-slate-100' }, row.getValue('maintenance_type')),
    },
    {
        accessorKey: 'maintenance_date',
        header: 'Service Date',
        cell: ({ row }) => h('div', {}, row.getValue('maintenance_date')),
    },
    {
        accessorKey: 'cost',
        header: 'Total Cost',
        cell: ({ row }) => h('div', { class: 'font-semibold text-emerald-600' }, `₱${parseFloat(row.getValue('cost')).toFixed(2)}`),
    },
    {
        accessorKey: 'service_provider',
        header: 'Service Provider / Shop',
        cell: ({ row }) => h('div', { class: 'truncate max-w-[150px]' }, row.getValue('service_provider')),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            let badgeClass = 'bg-slate-50 text-slate-700 border-slate-200';
            
            if (status === 'Completed') badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            if (status === 'Cancelled') badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
            if (status === 'In Progress') badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
            if (status === 'Scheduled') badgeClass = 'bg-blue-50 text-blue-700 border-blue-200';

            return h('div', { class: `capitalize font-mono text-xs px-2 py-0.5 rounded border inline-block ${badgeClass}` }, status || 'Scheduled');
        },
    },
    {
        id: 'plate_no',
        accessorFn: (row) => row.vehicle?.plate_no ?? null,
        header: 'Vehicle (Plate No.)',
        cell: ({ row }) => h('div', { class: 'font-mono text-xs' }, row.original.vehicle?.plate_no ?? '—'),
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

/* Dialog and Reactive UI Refresh Components */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const tableRefreshKey = ref(0);

// Options for the "Vehicle" dropdown — every vehicle regardless of status,
// populated from GET /TripTicketVehicle/lookup (id + plate_no only).
const vehicleOptions = ref<{ id: number; plate_no: string }[]>([]);
const loadingVehicleOptions = ref(false);

const fetchVehicleOptions = async () => {
    loadingVehicleOptions.value = true;
    try {
        const response = await axios.get('/TripTicketVehicle/lookup');
        vehicleOptions.value = response.data;
    } catch (error) {
        toast.error('Failed to load vehicle list.');
    } finally {
        loadingVehicleOptions.value = false;
    }
};

// Defensive fallback in case a vehicle referenced by an existing maintenance
// record was deleted after the fact — keeps the dropdown from going blank.
const ensureVehicleInOptions = async (vehicleId: number) => {
    if (!vehicleId || vehicleOptions.value.some((v) => v.id === vehicleId)) return;
    try {
        const response = await axios.get(`/TripTicketVehicle/${vehicleId}`);
        vehicleOptions.value = [
            { id: response.data.id, plate_no: response.data.plate_no },
            ...vehicleOptions.value,
        ];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this vehicle.
    }
};

/**
 * Bumps the cache-buster key AND explicitly refetches — relying on the
 * :key remount alone was inconsistent about refreshing immediately.
 */
const triggerTableRefresh = async () => {
    tableRefreshKey.value += 1;

    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

/* Validation Schema using z.enum to force AutoForm to render a native dropdown menu */
const schema = z.object({
    vehicle_id: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Vehicle ID reference is required' }).int().positive()),
    maintenance_date: z.string({ required_error: 'Maintenance date is required' }),
    maintenance_type: z.string({ required_error: 'Maintenance task type is required' }).max(50),
    description: z.string({ required_error: 'Detailed work summary is required' }),
    cost: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Expense amount is required' }).min(0)),
    service_provider: z.string({ required_error: 'Service provider or workshop name is required' }).max(100),
    status: z.enum(['Scheduled', 'In Progress', 'Cancelled', 'Completed'], { required_error: 'Operational status selection is required' }).default('Scheduled'),
    next_maintenance_date: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

const fieldconfig: any = {
    maintenance_date: { label: 'Service Date', inputProps: { type: 'date' } },
    maintenance_type: { label: 'Maintenance Type', inputProps: { type: 'text', placeholder: 'e.g., Change Oil, Brake Check, Repair' } },
    description: { label: 'Description of Work Done', inputProps: { type: 'textarea', placeholder: 'Describe the fixes, repairs, or procedures conducted...' } },
    cost: { label: 'Total Repair Cost (₱)', inputProps: { type: 'number', step: '0.01', placeholder: '0.00' } },
    service_provider: { label: 'Service Provider / Shop Name', inputProps: { type: 'text', placeholder: 'Workshop mechanic or facility title' } },
    status: { label: 'Current Log Status' }, // Dynamic enum rendering automatically maps this field into a clean selection layout
    next_maintenance_date: { label: 'Next Slotted Maintenance Date', inputProps: { type: 'date' } },
    remarks: { label: 'Log Specific Remarks', inputProps: { type: 'textarea', placeholder: 'Note down parts numbers or anomalies here...' } },
};

const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        vehicle_id: '' as any,
        maintenance_date: new Date().toISOString().slice(0, 10),
        maintenance_type: '',
        description: '',
        cost: '' as any,
        service_provider: '',
        status: 'Scheduled',
        next_maintenance_date: '',
        remarks: '',
    },
});

/* Form Processing Utilities */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

const handleOpenDialogForm = () => {
    resetForm();
    mode.value = 'create';
    showDialogForm.value = true;
    fetchVehicleOptions();
};

const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(baseentityurl, values);
            toast.success('Vehicle maintenance log entry successfully created.');
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success('Vehicle maintenance specifications successfully modified.');
        }

        resetForm();
        showDialogForm.value = false;
        
        await triggerTableRefresh();
        router.reload({ only: ['maintenances', 'errors'] });
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Validation conflict occurred. Verify field restrictions.');
        } else {
            toast.error('Database communications terminal service fault.');
        }
    }
};

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await fetchVehicleOptions();

        const response = await axios.get(`${baseentityurl}/${id}`);
        const data = response.data;

        await ensureVehicleInOptions(data.vehicle_id);

        form.setValues({
            vehicle_id: data.vehicle_id,
            maintenance_date: data.maintenance_date,
            maintenance_type: data.maintenance_type,
            description: data.description,
            cost: data.cost,
            service_provider: data.service_provider,
            status: data.status ?? 'Scheduled',
            next_maintenance_date: data.next_maintenance_date ?? '',
            remarks: data.remarks ?? '',
        });
        showDialogForm.value = true;
    } catch (error) {
        toast.error('Could not load specific data payload indices from server.');
    }
};

/* Delete Management Control */
const showDeleteDialog = ref(false);
const itemIDToDelete = ref<number | null>(null);

const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id;
    showDeleteDialog.value = true;
};

const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;
        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`);
        toast.success('Maintenance history trace purged permanently.');
        showDeleteDialog.value = false;
        
        await triggerTableRefresh();
        router.reload({ only: ['maintenances'] });
    } catch (error) {
        toast.error('Could not execute direct record index destruction.');
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600 hover:bg-blue-700 text-white" @click="handleOpenDialogForm">
                        <Plus class="h-4 w-4 mr-1" /> Log Maintenance Event
                    </Button>
                </div>
            </div>

            <ReusableDataTable
                :key="tableRefreshKey"
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[485px] max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} Fleet Maintenance Record</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Commit audit parameters detailing agency service vehicle breakdown, upkeep operations, or restoration actions.
                    </DialogDescription>
                    
                    <AutoForm
                        class="space-y-4 pt-2"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
                        <template #vehicle_id>
                            <FormField v-slot="{ componentField }" name="vehicle_id">
                                <FormItem>
                                    <FormLabel>Vehicle (Plate No.)</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingVehicleOptions ? 'Loading vehicles…' : 'Select a vehicle'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="vehicle in vehicleOptions"
                                                :key="vehicle.id"
                                                :value="String(vehicle.id)"
                                            >
                                                {{ vehicle.plate_no }}
                                            </SelectItem>
                                            <div v-if="!loadingVehicleOptions && vehicleOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No vehicles found.
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

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Purge Maintenance Ledger Log?`"
                :description="`Are you certain you want to clear this maintenance log trace? Discarding this ledger item permanently drops operational overhead statistics.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>