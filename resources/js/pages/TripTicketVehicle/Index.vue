<script setup lang="ts">
/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

/* Import Utilities */
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { ArrowUpDown, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref, nextTick } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table';
import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/TripTicketVehicle';
const baseentityname = 'Vehicle';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Vehicle Fleet Registry',
        href: baseentityurl,
    },
];

/* Interface exactly matching database schema fields */
export interface TripTicketVehicle {
    id: number;
    plate_no: string;
    vehicle_type: string;
    brand: string;
    model: string;
    year_model?: number | null;
    color: string;
    engine_no?: string | null;
    chassis_no?: string | null;
    capacity: number;
    status: 'Available' | 'In Use' | 'Maintenance' | 'Reserved';
    remarks?: string | null;
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

/* Define Table Columns with Dark Mode Aware Typography styling */
const columns: ColumnDef<TripTicketVehicle>[] = [
    {
        id: 'select',
        header: ({ table }) =>
            h(Checkbox, {
                modelValue: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
                'onUpdate:modelValue': (value) => table.toggleAllPageRowsSelected(!!value),
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
        accessorKey: 'plate_no',
        header: ({ column }) => {
            return h(
                Button,
                { variant: 'ghost', onClick: () => column.toggleSorting(column.getIsSorted() === 'asc') },
                () => ['Plate No.', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })]
            );
        },
        cell: ({ row }) => h('div', { class: 'font-mono font-bold text-slate-950 dark:text-slate-50 break-words whitespace-normal' }, row.getValue('plate_no')),
    },
    {
        accessorKey: 'brand',
        header: 'Brand / Make',
        cell: ({ row }) => h('div', { class: 'font-medium text-slate-800 dark:text-slate-200' }, row.getValue('brand')),
    },
    {
        accessorKey: 'model',
        header: 'Model Name',
        cell: ({ row }) => h('div', { class: 'text-slate-700 dark:text-slate-300' }, row.getValue('model')),
    },
    {
        accessorKey: 'vehicle_type',
        header: 'Classification',
        cell: ({ row }) => h('div', { class: 'text-slate-600 dark:text-slate-400' }, row.getValue('vehicle_type')),
    },
    {
        accessorKey: 'capacity',
        header: ({ column }) => {
            return h(
                Button,
                { variant: 'ghost', onClick: () => column.toggleSorting(column.getIsSorted() === 'asc') },
                () => ['Seats', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })]
            );
        },
        cell: ({ row }) => h('div', { class: 'text-center font-medium text-slate-700 dark:text-slate-300' }, `${row.getValue('capacity')} seats`),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            let badgeClass = 'text-green-600 dark:text-green-400 font-semibold';
            if (status === 'In Use') badgeClass = 'text-blue-600 dark:text-blue-400 font-semibold';
            if (status === 'Maintenance') badgeClass = 'text-red-600 dark:text-red-400 font-semibold';
            if (status === 'Reserved') badgeClass = 'text-amber-600 dark:text-amber-400 font-semibold';
            return h('div', { class: badgeClass }, status);
        },
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

/* State Controls */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

/* Zod Form Schema matching validation requirements */
const schema = z.object({
    plate_no: z.string({ required_error: 'Plate number notation required' }).toUpperCase().max(20),
    vehicle_type: z.string({ required_error: 'Classification type is required' }).max(50),
    brand: z.string({ required_error: 'Vehicle brand manufacturer is required' }).max(50),
    model: z.string({ required_error: 'Model description required' }).max(50),
    year_model: z.number().min(1900).max(new Date().getFullYear()).nullable().optional(),
    color: z.string({ required_error: 'Body paint metric is required' }).max(30),
    engine_no: z.string().max(50).nullable().optional(),
    chassis_no: z.string().max(50).nullable().optional(),
    capacity: z.number({ required_error: 'Passenger seating space is required' }).min(1).default(5),
    status: z.enum(['Available', 'In Use', 'Maintenance', 'Reserved']).default('Available'),
    remarks: z.string().nullable().optional(),
});

const fieldconfig: any = {
    plate_no: { label: 'Plate Number', inputProps: { placeholder: 'e.g. SAA-1234', class: 'uppercase' } },
    vehicle_type: { label: 'Vehicle Type', inputProps: { placeholder: 'e.g. SUV, Pickup, Sedan' } },
    brand: { label: 'Brand', inputProps: { placeholder: 'e.g. TOYOTA, MITSUBISHI' } },
    model: { label: 'Model', inputProps: { placeholder: 'e.g. HILUX, INNOVA' } },
    year_model: { label: 'Year Model', inputProps: { type: 'number' } },
    color: { label: 'Color', inputProps: { placeholder: 'e.g. White, Silver' } },
    engine_no: { label: 'Engine Number' },
    chassis_no: { label: 'Chassis Number' },
    capacity: { label: 'Seating Capacity', inputProps: { type: 'number' } },
    status: { label: 'Operational Status' },
    remarks: { label: 'Remarks / Allocation Details', inputProps: { type: 'textarea' } },
};

const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        plate_no: '',
        vehicle_type: '',
        brand: '',
        model: '',
        year_model: undefined,
        color: '',
        engine_no: '',
        chassis_no: '',
        capacity: 5,
        status: 'Available',
        remarks: '',
    },
});

const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

const handleOpenDialogForm = () => {
    resetForm();
    showDialogForm.value = true;
    mode.value = 'create';
};

const triggerTableRefresh = async () => {
    refreshKey.value += 1;
    await nextTick();
    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values);
            toast.success(`${baseentityname} added to deployment logs successfully.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success(`${baseentityname} records successfully amended.`);
        }

        resetForm();
        showDialogForm.value = false;
        await triggerTableRefresh();
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Validation parameters structural mismatch.');
        } else {
            toast.error('An unexpected record-lock or storage failure occurred.');
        }
    }
};

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;
        const response = await axios.get(`${baseentityurl}/${id}`);
        form.setValues(response.data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error reading row details:`, error);
        toast.error(`Unable to resolve vehicle structural parameters.`);
    }
};

/* Delete Sub-System Modules */
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
        toast.success(`${baseentityname} securely omitted from Active Fleet logs.`);
        showDeleteDialog.value = false;
        await triggerTableRefresh();
    } catch (error) {
        console.log(`Error processing deletion sequence:`, error);
        toast.error(`Purge sequence failed. Verify relational constraint chains.`);
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600 text-white hover:bg-blue-700" @click="handleOpenDialogForm">
                        <Plus class="h-4 mr-1"></Plus> Register {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Register' : 'Modify' }} Fleet {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Fill in complete logistical information below. Fields conform directly with property registry schemas. </DialogDescription>
                    
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <DialogFooter class="pt-4">
                            <Button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white w-full sm:w-auto">
                                {{ mode === 'create' ? 'Commit Vehicle' : 'Apply Changes' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Decommission ${baseentityname}`"
                :description="`Are you certain you want to strike this vehicle off active duty files permanently? This action cannot be reversed.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>