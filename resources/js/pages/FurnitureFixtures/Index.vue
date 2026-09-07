<script setup lang="ts">
/**
 * @file FurnitureFixtures.vue
 * @description Vue 3 Single File Component (SFC) for managing Furniture & Fixtures inventory records,
 *              featuring a TanStack-powered data table, dynamic modal forms via Zod and Vee-Validate,
 *              Axios CRUD operations, and Vue Sonner notification toasts.
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
const baseentityurl = '/FurnitureFixtures';
const baseentityname = 'Furniture & Fixtures';

/**
 * Breadcrumbs definition for page navigation header.
 */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/**
 * TypeScript interface explicitly matching database layout columns schema types.
 */
export interface InventFurnitureFixture {
    id: number;
    accCode: string;
    ParNo: number;
    Qty: number;
    Unit: string;
    Descrp: string;
    UnitCost: number;
    TotalCost: number;
    PropNo: string;
    AccPerson: string;
    Designation_office: string;
    dateRelease: string;
    Supplier: string;
    Remarks: string;
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
 * Definition of TanStack table columns containing row selectors, sortable specs, and cell formatting templates.
 */
const columns: ColumnDef<InventFurnitureFixture>[] = [
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
        accessorKey: 'PropNo',
        header: 'Property No',
        cell: ({ row }) => h('div', { class: 'font-mono text-xs font-bold text-gray-700' }, row.getValue('PropNo')),
    },
    {
        accessorKey: 'Descrp',
        header: ({ column }) => {
            return h(
                Button,
                { variant: 'ghost', onClick: () => column.toggleSorting(column.getIsSorted() === 'asc') },
                () => ['Description Specs', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })]
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words max-w-[200px] whitespace-normal' }, row.getValue('Descrp')),
    },
    {
        accessorKey: 'Qty',
        header: 'Qty',
        cell: ({ row }) => h('div', { class: 'text-center font-medium' }, row.getValue('Qty')),
    },
    {
        accessorKey: 'Unit',
        header: 'Unit',
    },
    {
        accessorKey: 'UnitCost',
        header: 'Unit Cost',
        cell: ({ row }) => h('div', { class: 'text-right font-mono text-emerald-600 font-medium' }, `₱${row.getValue('UnitCost')}`),
    },
    {
        accessorKey: 'TotalCost',
        header: 'Total Cost',
        cell: ({ row }) => h('div', { class: 'text-right font-mono text-emerald-700 font-bold' }, `₱${row.getValue('TotalCost')}`),
    },
    {
        accessorKey: 'AccPerson',
        header: 'Accountable Person',
        cell: ({ row }) => h('div', { class: 'font-medium text-sm' }, row.getValue('AccPerson')),
    },
    {
        accessorKey: 'Designation_office',
        header: 'Office / Designation',
    },
    {
        accessorKey: 'dateRelease',
        header: 'Release Date',
        cell: ({ row }) => {
            const rawDate = row.getValue('dateRelease') as string;
            return h('div', {}, rawDate ? rawDate.split('T')[0] : '');
        }
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

/* Dialog Form State Management */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

/**
 * Zod validation schema tracking structural constraints and formatting rules for database input values.
 */
const schema = z.object({
    accCode: z.string({ required_error: 'Account Code is required' }).max(50),
    ParNo: z.coerce.number({ required_error: 'Particular Number is required' }).int(),
    Qty: z.coerce.number({ required_error: 'Quantity is required' }).int().positive(),
    Unit: z.string({ required_error: 'Unit is required' }).max(100),
    Descrp: z.string({ required_error: 'Description is required' }).max(250),
    UnitCost: z.coerce.number({ required_error: 'Unit Cost is required' }).int(),
    TotalCost: z.coerce.number({ required_error: 'Total Cost is required' }).int(),
    PropNo: z.string({ required_error: 'Property Number is required' }).max(50),
    AccPerson: z.string({ required_error: 'Accountable Person is required' }).max(200),
    Designation_office: z.string({ required_error: 'Designation Office path is required' }).max(100),
    dateRelease: z.string({ required_error: 'Release Date is required' }).regex(/^\d{4}-\d{2}-\d{2}$/, { message: 'Must match YYYY-MM-DD format' }),
    Supplier: z.string({ required_error: 'Supplier details are required' }).max(150),
    Remarks: z.string({ required_error: 'Remarks parameter is required' }).max(150),
});

/**
 * Configuration mapping for AutoForm UI labels, input types, and field placeholders.
 */
const fieldconfig: any = {
    accCode: { label: 'Account Code', inputProps: { placeholder: 'e.g. 222_1_07_07_010' } },
    ParNo: { label: 'Particular No (ParNo)', inputProps: { type: 'number' } },
    Qty: { label: 'Quantity', inputProps: { type: 'number' } },
    Unit: { label: 'Measurement Unit', inputProps: { placeholder: 'e.g. pcs, sets, units' } },
    Descrp: { label: 'Specification Details Description', component: 'textarea', inputProps: { placeholder: 'Full properties descriptions details' } },
    UnitCost: { label: 'Unit Cost (Integer PHP)', inputProps: { type: 'number' } },
    TotalCost: { label: 'Total Cost (Integer PHP)', inputProps: { type: 'number' } },
    PropNo: { label: 'Property Number Label', inputProps: { placeholder: 'Inventory property tracking tag' } },
    AccPerson: { label: 'Accountable Person full name', inputProps: { placeholder: 'Firstname Lastname' } },
    Designation_office: { label: 'Designation / Office Assignment', inputProps: { placeholder: 'e.g. GSO Department Office' } },
    dateRelease: { label: 'Release Date', inputProps: { type: 'date', placeholder: 'YYYY-MM-DD' } },
    Supplier: { label: 'Supplier Enterprise Name' },
    Remarks: { label: 'Actionable Logs / Remarks', component: 'textarea' },
};

/* Vee-Validate form initialization with default values and schema binding */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        accCode: '',
        ParNo: undefined as any,
        Qty: undefined as any,
        Unit: '',
        Descrp: '',
        UnitCost: undefined as any,
        TotalCost: undefined as any,
        PropNo: '',
        AccPerson: '',
        Designation_office: '',
        dateRelease: '',
        Supplier: '',
        Remarks: '',
    },
});

/**
 * Resets form fields and clears active tracking items.
 */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

/**
 * Opens the creation dialog modal in create mode with blank fields.
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
 * Handles form submission for both create and update operations via Axios.
 */
const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values);
            toast.success(`${baseentityname} entry logged into standard database ledger.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success(`${baseentityname} records successfully synchronized.`);
        }

        resetForm();
        showDialogForm.value = false;
        await triggerTableRefresh();
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Validation parameters dropped by database limitations.');
        } else {
            toast.error('An unexpected runtime storage anomaly occurred.');
        }
    }
};

/**
 * Fetches record details by ID, adjusts date configurations, and opens the dialog modal in edit mode.
 */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;
        const response = await axios.get(`${baseentityurl}/${id}`);
        
        // Clean up date structure for HTML5 input mapping compatibility
        if (response.data.dateRelease) {
            response.data.dateRelease = response.data.dateRelease.split('T')[0];
        }
        
        form.setValues(response.data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error locating data identifier row:`, error);
        toast.error(`Unable to view targeted configuration properties parameters.`);
    }
};

/* Delete Confirmation Dialog States */
const showDeleteDialog = ref(false);
const itemIDToDelete = ref<number | null>(null);

/**
 * Opens the delete confirmation dialog for a specific record.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id;
    showDeleteDialog.value = true;
};

/**
 * Sends a delete request via Axios for the selected record and refreshes the table.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;

        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`);
        toast.success(`${baseentityname} record deleted securely.`);
        showDeleteDialog.value = false;
        await triggerTableRefresh();
    } catch (error) {
        console.log(`Failed to process delete action:`, error);
        toast.error(`Database rejected request due to active foreign tracking dependencies.`);
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <!-- Action Header Toolbar -->
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600" @click="handleOpenDialogForm">
                        <Plus class="h-4"></Plus> Create {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <!-- Data Table Component with Key-Based Cache Buster -->
            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <!-- Create / Update Dialog Modal Form -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[500px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Log New' : 'Update Configured' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Provide structural data metrics fitting schema specifications. </DialogDescription>
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <DialogFooter class="pt-2">
                            <Button type="submit" class="bg-yellow-600 w-full sm:w-auto">
                                {{ mode === 'create' ? 'Save' : 'Update' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Alert Dialog -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Purge ${baseentityname}`"
                :description="`Are you certain you want to delete this asset entry record permanently? This action cannot be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>