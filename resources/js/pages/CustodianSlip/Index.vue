<script setup lang="ts">
/**
 * @file CustodianSlip.vue
 * @description Vue 3 Single File Component (SFC) for managing Inventory Custodian Slip (ICS) records,
 *              featuring a TanStack-powered data table, dynamic modal forms via Zod and Vee-Validate,
 *              Axios CRUD operations, and Vue Sonner notification toasts.
 */

/* Import Components */
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
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

/* Import Types */
import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/CustodianSlip';
const baseentityname = 'Custodian Slip';

/* Breadcrumbs definition for page navigation header */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/**
 * TypeScript interface matching database columns explicitly for inventory custodian slips.
 */
export interface InventCustodianSlip {
    id: number;
    Qty: number;
    Unit: string;
    Descrp: string;
    Invent_Item_No: string;
    Ez_Useful_Life: string;
    ReceivedBy_Name: string;
    ReceivedBy_Position: string;
    ReceiveBy_Date: string;
    ReceivedFrom_Name: string;
    ReceivedFrom_Position: string;
    ReceiveFrom_Date: string;
    ICS: number;
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
 * Definition of TanStack table columns including row selection, sorting, and cell layout configurations.
 */
const columns: ColumnDef<InventCustodianSlip>[] = [
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
        accessorKey: 'ICS',
        header: 'ICS No',
        cell: ({ row }) => h('div', { class: 'break-words font-mono font-bold' }, row.getValue('ICS')),
    },
    {
        accessorKey: 'Invent_Item_No',
        header: 'Inventory Item No',
        cell: ({ row }) => h('div', { class: 'break-words font-medium' }, row.getValue('Invent_Item_No')),
    },
    {
        accessorKey: 'Descrp',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Description', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Descrp')),
    },
    {
        accessorKey: 'Qty',
        header: 'Qty',
        cell: ({ row }) => h('div', { class: 'text-center font-semibold' }, row.getValue('Qty')),
    },
    {
        accessorKey: 'Unit',
        header: 'Unit',
        cell: ({ row }) => h('div', { class: 'break-words' }, row.getValue('Unit')),
    },
    {
        accessorKey: 'ReceivedBy_Name',
        header: 'Received By',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal font-medium' }, row.getValue('ReceivedBy_Name')),
    },
    {
        accessorKey: 'Ez_Useful_Life',
        header: 'Est. Useful Life',
        cell: ({ row }) => h('div', { class: 'break-words text-gray-600' }, row.getValue('Ez_Useful_Life')),
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

/* Form Dialog and Cache Buster Signals */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

/**
 * Zod validation schema matching maximum database length restrictions and strict required inputs.
 */
const schema = z.object({
    Qty: z.coerce.number({ required_error: 'Quantity is required' }).int(),
    Unit: z.string({ required_error: 'Unit type is required' }).max(50),
    Descrp: z.string({ required_error: 'Description is required' }).max(250),
    Invent_Item_No: z.string({ required_error: 'Inventory item number is required' }).max(50),
    Ez_Useful_Life: z.string({ required_error: 'Estimated useful life span description is required' }).max(50),
    ReceivedBy_Name: z.string({ required_error: 'Receiver name is required' }).max(50),
    ReceivedBy_Position: z.string({ required_error: 'Receiver position is required' }).max(50),
    ReceiveBy_Date: z.string({ required_error: 'Receiver transaction date is required' }),
    ReceivedFrom_Name: z.string({ required_error: 'Sender name is required' }).max(50),
    ReceivedFrom_Position: z.string({ required_error: 'Sender position is required' }).max(50),
    ReceiveFrom_Date: z.string({ required_error: 'Sender transaction date is required' }),
    ICS: z.coerce.number({ required_error: 'ICS tracking reference number is required' }).int(),
});

/**
 * UI field configuration mapping for AutoForm components, labels, placeholders, and input types.
 */
const fieldconfig: any = {
    Qty: { label: 'Quantity', inputProps: { type: 'number' } },
    Unit: { label: 'Unit of Measure', inputProps: { placeholder: 'e.g., pcs, units, sets' } },
    Descrp: { label: 'Item Description', component: 'textarea', inputProps: { placeholder: 'Enter properties, serials, make, and brand details' } },
    Invent_Item_No: { label: 'Inventory Item Number', inputProps: { placeholder: 'Property Control Item reference number' } },
    Ez_Useful_Life: { label: 'Estimated Useful Life', inputProps: { placeholder: 'e.g., 5 Years, 10 Months' } },
    ReceivedBy_Name: { label: 'Received By (Full Name)', inputProps: { placeholder: 'Accountable recipient name' } },
    ReceivedBy_Position: { label: 'Received By (Official Position)', inputProps: { placeholder: 'Job title' } },
    ReceiveBy_Date: { label: 'Received By (Date)', inputProps: { type: 'date' } },
    ReceivedFrom_Name: { label: 'Received From (Full Name)', inputProps: { placeholder: 'Issuing officer name' } },
    ReceivedFrom_Position: { label: 'Received From (Official Position)', inputProps: { placeholder: 'Job title' } },
    ReceiveFrom_Date: { label: 'Received From (Date)', inputProps: { type: 'date' } },
    ICS: { label: 'ICS Serial Number Number', inputProps: { type: 'number', placeholder: 'Enter official Slip serial number' } },
};

/* Vee-Validate form initialization with default values and schema binding */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        Qty: 1,
        Unit: '',
        Descrp: '',
        Invent_Item_No: '',
        Ez_Useful_Life: '',
        ReceivedBy_Name: '',
        ReceivedBy_Position: '',
        ReceiveBy_Date: '',
        ReceivedFrom_Name: '',
        ReceivedFrom_Position: '',
        ReceiveFrom_Date: '',
        ICS: 0,
    },
});

/**
 * Resets the form state and clears the active editing item ID.
 */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

/**
 * Prepares and opens the dialog modal for creating a new custodian slip entry.
 */
const handleOpenDialogForm = () => {
    resetForm();
    showDialogForm.value = true;
    mode.value = 'create';
};

/**
 * Triggers a component re-render cache-bust and fetches fresh table rows.
 */
const triggerTableRefresh = async () => {
    refreshKey.value += 1;
    await nextTick();
    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

/**
 * Handles form submission for both create and update endpoints via Axios.
 */
const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values);
            toast.success(`${baseentityname} generated successfully.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success(`${baseentityname} updated successfully.`);
        }

        resetForm();
        showDialogForm.value = false;
        await triggerTableRefresh();

    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Validation parameters failing database checks.');
        } else {
            toast.error('An unexpected storage exception occurred.');
        }
    }
};

/**
 * Fetches record data by ID and opens the dialog modal in edit mode.
 */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;
        const response = await axios.get(`${baseentityurl}/${id}`);
        form.setValues(response.data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error parsing ${baseentityname} tracking information:`, error);
        toast.error(`Failed to isolate target ${baseentityname}.`);
    }
};

/* Delete Confirmation Dialog States */
const showDeleteDialog = ref(false);
const itemIDToDelete = ref<number | null>(null);

/**
 * Opens the alert dialog confirmation to delete a specific custodian slip record.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id;
    showDeleteDialog.value = true;
};

/**
 * Sends a delete request via Axios for the selected record and refreshes the data table.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;

        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`);
        toast.success(`${baseentityname} records cleared successfully.`);
        showDeleteDialog.value = false;
        await triggerTableRefresh();
        
    } catch (error) {
        console.log(`Error cleaning database rows:`, error);
        toast.error(`Failed to wipe specified ${baseentityname}.`);
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600" @click="handleOpenDialogForm"> <Plus class="h-4"></Plus> Create {{ baseentityname }} </Button>
                </div>
            </div>

            <!-- Data Table Component with Key-Based Cache Buster -->
            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <!-- Create / Update Dialog Modal Form -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[480px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Provide operational criteria parameters to log an Inventory Custodian Slip (ICS). </DialogDescription>
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <DialogFooter class="pt-2">
                            <Button type="submit" class="bg-yellow-600 w-full sm:w-auto">
                                {{ mode === 'create' ? 'Create' : 'Update' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Alert Dialog -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Wipe ${baseentityname}`"
                :description="`Are you completely sure you want to delete this custodian record? This process is irreversible.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>