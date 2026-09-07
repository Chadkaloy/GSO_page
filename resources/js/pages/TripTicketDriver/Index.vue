<script setup lang="ts">
/**
 * @file TripTicketDriver Index Component (`Index.vue`)
 * @description Vue 3 Single File Component (SFC) for managing driver registries,
 *              incorporating a dynamic TanStack table, Zod validation schemas, 
 *              Axios-powered CRUD handlers, and interactive modal dialog forms.
 */

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
const baseentityurl = '/TripTicketDriver';
const baseentityname = 'Driver';

/**
 * Breadcrumbs configuration array for page layouts.
 */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Driver Registry Ledger',
        href: baseentityurl,
    },
];

/**
 * TypeScript interface matching database columns for driver records exactly.
 */
export interface TripTicketDriver {
    id: number;
    driver_code: string;
    full_name: string;
    license_no: string;
    license_expiry?: string | null;
    contact_no?: string | null;
    address?: string | null;
    status: string;
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

/**
 * TanStack table column configuration definitions with custom cell renderers and styling.
 */
const columns: ColumnDef<TripTicketDriver>[] = [
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
        accessorKey: 'driver_code',
        header: ({ column }) => {
            return h(
                Button,
                { variant: 'ghost', onClick: () => column.toggleSorting(column.getIsSorted() === 'asc') },
                () => ['Code', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })]
            );
        },
        cell: ({ row }) => h('div', { class: 'font-mono font-bold text-slate-950 dark:text-slate-50 break-words whitespace-normal' }, row.getValue('driver_code')),
    },
    {
        accessorKey: 'full_name',
        header: 'Driver Name',
        cell: ({ row }) => h('div', { class: 'font-medium text-slate-800 dark:text-slate-200' }, row.getValue('full_name')),
    },
    {
        accessorKey: 'license_no',
        header: 'License Number',
        cell: ({ row }) => h('div', { class: 'text-slate-700 dark:text-slate-300 font-mono text-sm' }, row.getValue('license_no')),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            let statusClass = 'text-green-600 dark:text-green-400 font-semibold';
            if (status !== 'Active') statusClass = 'text-amber-600 dark:text-amber-400 font-semibold';
            return h('div', { class: statusClass }, status);
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

/* Dialog and Table Component State Control References */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

/**
 * Zod validation schema matching database storage constraints and required input formats.
 */
const schema = z.object({
    driver_code: z.string({ required_error: 'Driver code assignment notation required' }).toUpperCase().max(20),
    full_name: z.string({ required_error: 'Full personnel designation name is required' }).max(100),
    license_no: z.string({ required_error: 'Official operator license record is required' }).toUpperCase().max(50),
    license_expiry: z.string().nullable().optional(),
    contact_no: z.string().max(20).nullable().optional(),
    address: z.string().max(200).nullable().optional(),
    status: z.string().max(20).default('Active'),
    remarks: z.string().nullable().optional(),
});

/**
 * Configuration mapping for AutoForm field labels, attributes, and input types.
 */
const fieldconfig: any = {
    driver_code: { label: 'Driver Code ID', inputProps: { placeholder: 'e.g. DRV-2026-001', class: 'uppercase' } },
    full_name: { label: 'Full Name', inputProps: { placeholder: 'First Name Middle Name Last Name' } },
    license_no: { label: 'Driver License No.', inputProps: { placeholder: 'e.g. N01-12-345678', class: 'uppercase' } },
    license_expiry: { label: 'License Expiration Date', inputProps: { type: 'date' } },
    contact_no: { label: 'Contact Phone / Mobile Number', inputProps: { placeholder: 'e.g. 0917XXXXXXX' } },
    address: { label: 'Residential Address', inputProps: { placeholder: 'Street, Barangay, City/Municipality' } },
    status: { label: 'Current Logistical Status', inputProps: { placeholder: 'Active, On Leave, Inactive' } },
    remarks: { label: 'Remarks / Medical Conditions / Limitations', inputProps: { type: 'textarea' } },
};

/**
 * Vee-Validate form instance initialization using Zod schemas and default values.
 */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        driver_code: '',
        full_name: '',
        license_no: '',
        license_expiry: '',
        contact_no: '',
        address: '',
        status: 'Active',
        remarks: '',
    },
});

/**
 * Resets form state values and clears selected item IDs.
 */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

/**
 * Opens the creation dialog form in 'create' mode.
 */
const handleOpenDialogForm = () => {
    resetForm();
    showDialogForm.value = true;
    mode.value = 'create';
};

/**
 * Triggers table data refreshes and re-renders via component reference methods.
 */
const triggerTableRefresh = async () => {
    refreshKey.value += 1;
    await nextTick();
    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

/**
 * Submits form data payloads via Axios to create or update driver records.
 */
const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values);
            toast.success(`${baseentityname} records created and active.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success(`${baseentityname} records structurally amended.`);
        }

        resetForm();
        showDialogForm.value = false;
        await triggerTableRefresh();
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Logistical indexing data validation errors detected.');
        } else {
            toast.error('An unexpected storage error occurred.');
        }
    }
};

/**
 * Fetches specific driver records by ID and populates the modal form in 'edit' mode.
 */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;
        const response = await axios.get(`${baseentityurl}/${id}`);
        form.setValues(response.data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error resolving driver parameters:`, error);
        toast.error(`Failed to map target ${baseentityname} attributes.`);
    }
};

/* Delete Confirmation Dialog State Controls */
const showDeleteDialog = ref(false);
const itemIDToDelete = ref<number | null>(null);

/**
 * Opens the delete confirmation dialog for a specific record ID.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id;
    showDeleteDialog.value = true;
};

/**
 * Sends a DELETE request to backend services and updates the data table.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;

        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`);
        toast.success(`${baseentityname} records cleared successfully.`);
        showDeleteDialog.value = false;
        await triggerTableRefresh();
    } catch (error) {
        console.log(`Error processing drop cascade:`, error);
        toast.error(`Failed to completely drop driver file. Verify system locks.`);
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
                    <Button v-if="permissions.create" class="bg-blue-600 text-white hover:bg-blue-700" @click="handleOpenDialogForm">
                        <Plus class="h-4 mr-1"></Plus> Register {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <!-- Reusable data table component binding columns and table reference -->
            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <!-- Creation and update modal dialog form component -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Onboard' : 'Update' }} Personnel {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Provide standard personnel identity data for logistics file management. Fields mirror active licensing standards. </DialogDescription>
                    
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <DialogFooter class="pt-4">
                            <Button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white w-full sm:w-auto">
                                {{ mode === 'create' ? 'Save Operator File' : 'Save Changes' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Decommissioning alert confirmation dialog component -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Decommission ${baseentityname} Account`"
                :description="`Are you certain you want to purge this driver's record log from active registry entries? Historical log links will remain locked.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>