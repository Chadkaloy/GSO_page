<script setup lang="ts">
/**
 * @file BincardRecord.vue
 * @description Vue 3 Single File Component (SFC) for tracking Bincard inventory ledger items,
 *              featuring a TanStack-powered data table, dynamic Zod validation forms,
 *              Axios CRUD operations, and reactive notifications using Vue Sonner.
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
const baseentityurl = '/Bincard'; 
const baseentityname = 'Bincard Record'; 

/* Breadcrumbs definition for page header navigation */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bincard Record',
        href: baseentityurl,
    },
];

/**
 * TypeScript interface matching the database structure for bincard ledger entries.
 */
interface BincardRecord {
    id: number;
    bin_Date: string;
    Supplier: string;
    Descrp: string;
    Qty: string;
    Issued: number;
    Balance: number;
    PoNo: string;
}

/* Reactivity Refresh Key for cache-busting and table synchronization */
const refreshKey = ref(0);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const triggerTableRefresh = async () => {
    refreshKey.value++;
    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

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
 * Definition of TanStack table columns mapped directly to database keys,
 * including selection boxes, sortable headers, and action dropdowns.
 */
const columns: ColumnDef<BincardRecord>[] = [
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
        accessorKey: 'id',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Bin ID', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'font-mono font-semibold text-center' }, row.getValue('id')),
    },
    {
        accessorKey: 'bin_Date', 
        header: 'Transaction Date',
        cell: ({ row }) => h('div', { class: 'whitespace-nowrap font-medium' }, row.getValue('bin_Date')),
    },
    {
        accessorKey: 'PoNo', 
        header: 'PO Number',
        cell: ({ row }) => h('div', { class: 'font-mono text-xs font-semibold' }, row.getValue('PoNo')),
    },
    {
        accessorKey: 'Supplier', 
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Supplier Entity', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words font-medium' }, row.getValue('Supplier')),
    },
    {
        accessorKey: 'Descrp', 
        header: 'Item Description',
        cell: ({ row }) => h('div', { class: 'break-words text-sm max-w-[250px]' }, row.getValue('Descrp')),
    },
    {
        accessorKey: 'Qty', 
        header: 'Qty Received',
        cell: ({ row }) => h('div', { class: 'text-center' }, row.getValue('Qty')),
    },
    {
        accessorKey: 'Issued', 
        header: 'Qty Issued',
        cell: ({ row }) => h('div', { class: 'text-center font-medium text-orange-600' }, row.getValue('Issued')),
    },
    {
        accessorKey: 'Balance', 
        header: 'Stock Balance',
        cell: ({ row }) => h('div', { class: 'text-center font-bold text-green-600' }, row.getValue('Balance')),
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
                    onEdit: permissions.value.edit ? () => handleEdit(rowitem.id) : undefined,
                    onDelete: permissions.value.delete ? () => openDeleteDialog(rowitem.id) : undefined,
                }),
            );
        },
    },
];

/* Dialog Management States */
const showDialogForm = ref(false); 
const mode = ref('create'); 
const itemID = ref<number | null>(null); 

/**
 * Zod validation schema matching exact database types and constraints.
 */
const schema = z.object({
    bin_Date: z.string({ required_error: 'Transaction date is required' }),
    Supplier: z.string({ required_error: 'Supplier title is required' }).min(2).max(100),
    Descrp: z.string({ required_error: 'Item description details are required' }).min(2).max(250),
    Qty: z.string({ required_error: 'Quantity received profile value is required' }).max(10),
    Issued: z.number({ required_error: 'Issued allocation tally is required' }).int(),
    Balance: z.number({ required_error: 'Remaining balance count is required' }),
    PoNo: z.string({ required_error: 'PO reference code is required' }).min(1).max(50),
});

/**
 * UI field configuration mapping for AutoForm labels, types, and input placeholders.
 */
const fieldconfig: any = {
    bin_Date: {
        label: 'Tally Entry Date',
        inputProps: { type: 'date' },
    },
    PoNo: {
        label: 'Purchase Order Reference (PoNo)',
        inputProps: { placeholder: 'e.g., PO-2026-8874' },
    },
    Supplier: {
        label: 'Supplier Business Title Entity',
        inputProps: { placeholder: 'Enter vendor name' },
    },
    Descrp: {
        label: 'Stock Specifications & Component Description',
        component: 'textarea',
        inputProps: { placeholder: 'Enter complete item specifications...' },
    },
    Qty: {
        label: 'Volume Quantity Received',
        inputProps: { placeholder: 'e.g., 50 boxes' },
    },
    Issued: {
        label: 'Tally Quantity Dispatched / Issued',
        inputProps: { type: 'number' },
    },
    Balance: {
        label: 'Calculated Remaining Stock Balance Tally',
        inputProps: { type: 'number', step: 'any' },
    },
};

/* Vee-Validate form instantiation with default values and schema binding */
const form = useForm({
    validationSchema: toTypedSchema(schema), 
    initialValues: {
        bin_Date: new Date().toISOString().split('T')[0],
        Supplier: '',
        Descrp: '',
        Qty: '',
        Issued: 0,
        Balance: 0,
        PoNo: '',
    },
});

/**
 * Resets form states and clears the active editing item ID.
 */
const resetForm = () => {
    form.resetForm(); 
    itemID.value = null; 
};

/**
 * Prepares and opens the dialog modal for creating a new bincard entry.
 */
const handleOpenDialogForm = () => {
    resetForm();
    mode.value = 'create'; 
    showDialogForm.value = true; 
};

/**
 * Handles form submissions for both creation and modification API requests.
 */
const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values); 
            toast.success(`${baseentityname} cataloged to ledger log successfully.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values); 
            toast.success(`${baseentityname} adjustments saved securely.`);
        }

        resetForm(); 
        await triggerTableRefresh(); 
        showDialogForm.value = false; 
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors); 
            toast.error('Validation conflict. Review item parameters.');
        } else {
            toast.error('Internal server connectivity issue encountered.');
        }
    }
};

/**
 * Fetches record details by ID, loads them into the form, and opens the dialog in edit mode.
 */
const handleEdit = async (id: number) => {
    try {
        resetForm();
        mode.value = 'edit'; 
        itemID.value = id; 
        const response = await axios.get(`${baseentityurl}/${id}`); 
        form.setValues(response.data); 
        showDialogForm.value = true; 
    } catch (error) {
        toast.error(`Unable to locate targeted ${baseentityname} profile data.`);
    }
};

/* Deletion confirmation dialog states */
const showDeleteDialog = ref(false); 
const itemIDToDelete = ref<number | null>(null); 

/**
 * Opens the alert dialog to confirm item deletion.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id; 
    showDeleteDialog.value = true; 
};

/**
 * Executes the delete request via Axios and refreshes the data table.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;
        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`); 
        toast.success(`${baseentityname} removed from active database logging rows.`);
        await triggerTableRefresh(); 
        showDeleteDialog.value = false; 
    } catch (error) {
        toast.error(`Constraint blocking absolute removal of stock item record.`);
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600" @click="handleOpenDialogForm">
                        <Plus class="h-4"></Plus> Log {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <!-- Reusable data table component bound with refresh key cache-busting -->
            <ReusableDataTable
                :key="refreshKey"
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <!-- Create / Update Dialog Form Modal -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[475px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Append' : 'Edit Existing' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Maintain explicit balance quantities and stock allocations for material assets.
                    </DialogDescription>
                    <AutoForm
                        class="space-y-4"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
                        <DialogFooter class="pt-2">
                            <Button type="submit" class="bg-yellow-600 w-full">
                                {{ mode === 'create' ? 'Commit Entry Log' : 'Save Structural Modifications' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Alert Dialog -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Purge ${baseentityname} Reference row?`"
                :description="`Confirm absolute removal of this specific bincard item entry from persistent records?`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>