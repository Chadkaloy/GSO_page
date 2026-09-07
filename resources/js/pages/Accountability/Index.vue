<script setup lang="ts">
/**
 * @file EmployeeAccountabilityCard.vue
 * @description Vue 3 Single File Component (SFC) for managing Employee Accountability Cards,
 *              featuring a reusable data table, dynamic modal form handling via Zod and Vee-Validate,
 *              CRUD operations using Axios, and notification alerts via Vue Sonner.
 */

/* Import Components */
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
const baseentityurl = '/Accountability';
const baseentityname = 'Employee Accountability Card';

/* Breadcrumbs definition for page navigation header */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/**
 * Interface matching database columns exactly for TypeScript type safety.
 */
export interface EmployeeAccountabilityCard {
    id: number;
    Emp_ID: number;
    // Eager-loaded from EmpAccountabilityCard::employee(). Laravel keeps this
    // key as-is since "employee" has no uppercase letters to convert.
    employee?: { accID: number; fullName: string } | null;
    ItemSetID: string;
    itemCode: string;
    // Eager-loaded from EmpAccountabilityCard::inventoryItem(). Laravel
    // serializes this relation as snake_case: "inventory_item".
    inventory_item?: { 'AC_COA_Cir_04-08': string; 'AC_Name(Old)': string | null; 'AC_name(New)': string | null } | null;
    ParNo: string;
    Qty: number;
    Unit: string;
    Descrp: string;
    SN: string;
    PropNo: string;
    Amount: number;
    TransferTo: string;
    Remarks: string;
    DateTurnOver: string;
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
 * Definition of TanStack table columns including row selection, sorting, and cell templates.
 */
const columns: ColumnDef<EmployeeAccountabilityCard>[] = [
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
        accessorKey: 'Emp_ID',
        header: 'Employee ID',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal font-mono' }, row.getValue('Emp_ID')),
    },
    {
        id: 'employee_name',
        accessorFn: (row) => row.employee?.fullName ?? null,
        header: 'Employee Name',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.original.employee?.fullName ?? '—'),
    },
    {
        accessorKey: 'itemCode',
        header: 'Item Code',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('itemCode')),
    },
    {
        id: 'item_description',
        accessorFn: (row) => row.inventory_item?.['AC_Name(Old)'] ?? row.inventory_item?.['AC_name(New)'] ?? null,
        header: 'Item Description',
        cell: ({ row }) => {
            const item = row.original.inventory_item;
            const label = item?.['AC_Name(Old)'] ?? item?.['AC_name(New)'] ?? '—';
            return h('div', { class: 'break-words whitespace-normal' }, label);
        },
    },
    {
        accessorKey: 'ItemSetID',
        header: 'Item Set ID',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('ItemSetID')),
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
        accessorKey: 'ParNo',
        header: 'PAR No',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('ParNo')),
    },
    {
        accessorKey: 'Qty',
        header: 'Qty',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Qty')),
    },
    {
        accessorKey: 'Unit',
        header: 'Unit',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Unit')),
    },
    {
        accessorKey: 'SN',
        header: 'Serial No',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('SN')),
    },
    {
        accessorKey: 'PropNo',
        header: 'Property No',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('PropNo')),
    },
    {
        accessorKey: 'Amount',
        header: 'Amount',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal text-right' }, Number(row.getValue('Amount')).toFixed(2)),
    },
    {
        accessorKey: 'TransferTo',
        header: 'Transfer To',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('TransferTo')),
    },
    {
        accessorKey: 'DateTurnOver',
        header: 'Turn Over Date',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal text-center' }, row.getValue('DateTurnOver')),
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

/* Dialog and Table Cache-Bust States */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

// Options for the "Employee Account ID" dropdown — every employee record,
// shown by fullName while the value submitted is still accID.
const employeeOptions = ref<{ accID: number; fullName: string }[]>([]);
const loadingEmployeeOptions = ref(false);

const fetchEmployeeOptions = async () => {
    loadingEmployeeOptions.value = true;
    try {
        const response = await axios.get('/Employee/lookup');
        employeeOptions.value = response.data;
    } catch (error) {
        toast.error('Failed to load employee list.');
    } finally {
        loadingEmployeeOptions.value = false;
    }
};

// Defensive fallback in case a linked employee record was deleted after the
// fact — keeps the dropdown from going blank when editing that row.
const ensureEmployeeInOptions = async (accID: number) => {
    if (!accID || employeeOptions.value.some((e) => e.accID === accID)) return;
    try {
        const response = await axios.get(`/Employee/${accID}`);
        employeeOptions.value = [
            { accID: response.data.accID, fullName: response.data.fullName },
            ...employeeOptions.value,
        ];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this employee.
    }
};

// Options for the "Item Code" dropdown — every inventory dictionary entry,
// shown by its name while the value submitted is the item code itself
// (AC_COA_Cir_04-08), not a numeric id.
const itemOptions = ref<{ 'AC_COA_Cir_04-08': string; 'AC_Name(Old)': string | null; 'AC_name(New)': string | null }[]>([]);
const loadingItemOptions = ref(false);

const fetchItemOptions = async () => {
    loadingItemOptions.value = true;
    try {
        const response = await axios.get('/Inventory/lookup');
        itemOptions.value = response.data;
    } catch (error) {
        toast.error('Failed to load inventory item list.');
    } finally {
        loadingItemOptions.value = false;
    }
};

// Defensive fallback in case an item code was deleted from the dictionary
// after the fact — keeps the dropdown from going blank when editing that row.
const ensureItemInOptions = async (code: string) => {
    if (!code || itemOptions.value.some((i) => i['AC_COA_Cir_04-08'] === code)) return;
    try {
        const response = await axios.get(`/Inventory/lookup-by-code/${code}`);
        itemOptions.value = [response.data, ...itemOptions.value];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this item code.
    }
};

/**
 * Zod validation schema matching database constraints and field rules.
 */
const schema = z.object({
    Emp_ID: z.coerce.number({ required_error: 'Employee account ID is required' }),
    ItemSetID: z.string({ required_error: 'Item Set ID is required' }).max(25),
    itemCode: z.string({ required_error: 'Item Code is required' }).max(25),
    ParNo: z.string({ required_error: 'PAR number is required' }).max(50),
    Qty: z.coerce.number({ required_error: 'Quantity is required' }).int(),
    Unit: z.string({ required_error: 'Unit is required' }).max(50),
    Descrp: z.string({ required_error: 'Description is required' }).max(150),
    SN: z.string({ required_error: 'Serial number is required' }).max(50),
    PropNo: z.string({ required_error: 'Property number is required' }).max(50),
    Amount: z.coerce.number({ required_error: 'Amount is required' }),
    TransferTo: z.string({ required_error: 'Transfer field is required' }).max(100),
    Remarks: z.string({ required_error: 'Remarks are required' }).max(200),
    DateTurnOver: z.string({ required_error: 'Date of turnover is required' }),
});

/**
 * Configuration mapping for AutoForm UI labels, placeholders, and input types.
 */
const fieldconfig: any = {
    Emp_ID: { label: 'Employee Account ID', inputProps: { type: 'number', placeholder: 'Enter Acc ID' } },
    ItemSetID: { label: 'Item Set ID', inputProps: { placeholder: 'Enter item set ID' } },
    itemCode: { label: 'Item Code', inputProps: { placeholder: 'Enter item code' } },
    ParNo: { label: 'PAR Number', inputProps: { placeholder: 'Enter PAR number' } },
    Qty: { label: 'Quantity', inputProps: { type: 'number', placeholder: 'Enter quantity' } },
    Unit: { label: 'Unit', inputProps: { placeholder: 'Enter unit (e.g. pcs, unit)' } },
    Descrp: { label: 'Description', component: 'textarea', inputProps: { placeholder: 'Enter card item details' } },
    SN: { label: 'Serial Number (SN)', inputProps: { placeholder: 'Enter serial number' } },
    PropNo: { label: 'Property Number', inputProps: { placeholder: 'Enter property number' } },
    Amount: { label: 'Amount', inputProps: { type: 'number', step: '0.01', placeholder: '0.00' } },
    TransferTo: { label: 'Transfer To', inputProps: { placeholder: 'Enter transfer records location' } },
    Remarks: { label: 'Remarks', component: 'textarea', inputProps: { placeholder: 'Enter notes' } },
    DateTurnOver: { label: 'Date Turn Over', inputProps: { type: 'date' } },
};

/* Vee-Validate form initialization with default values and validation schema */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        Emp_ID: 0,
        ItemSetID: '',
        itemCode: '',
        ParNo: '',
        Qty: 1,
        Unit: '',
        Descrp: '',
        SN: '',
        PropNo: '',
        Amount: 0,
        TransferTo: '',
        Remarks: '',
        DateTurnOver: '',
    },
});

/**
 * Resets the form inputs and clears the active editing item ID.
 */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

/**
 * Opens the creation dialog form and resets fields.
 */
const handleOpenDialogForm = () => {
    resetForm();
    showDialogForm.value = true;
    mode.value = 'create';
    fetchEmployeeOptions();
    fetchItemOptions();
};

/**
 * Triggers a cache-bust refresh for the data table component.
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
            toast.success(`${baseentityname} created successfully.`);
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
            toast.error('Validation failed. Please check your input.');
        } else {
            toast.error('An unexpected error occurred.');
        }
    }
};

/**
 * Fetches record data by ID and opens the dialog in edit mode.
 */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await fetchEmployeeOptions();
        await fetchItemOptions();

        const response = await axios.get(`${baseentityurl}/${id}`);
        const data = response.data;

        await ensureEmployeeInOptions(data.Emp_ID);
        await ensureItemInOptions(data.itemCode);

        form.setValues(data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error fetching ${baseentityname} data:`, error);
        toast.error(`Failed to fetch ${baseentityname} data.`);
    }
};

/* Delete Dialog Confirmation Operations */
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
        toast.success(`${baseentityname} deleted successfully.`);
        showDeleteDialog.value = false;
        await triggerTableRefresh();
        
    } catch (error) {
        console.log(`Error deleting ${baseentityname}:`, error);
        toast.error(`Failed to delete ${baseentityname}. Please try again.`);
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

            <!-- Built-in refreshKey cache-busting pipeline -->
            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <!-- Create / Update Dialog Form Modal -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[425px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Use this form to manage employee accountability record metrics. </DialogDescription>
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <template #Emp_ID>
                            <FormField v-slot="{ componentField }" name="Emp_ID">
                                <FormItem>
                                    <FormLabel>Employee Account ID</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingEmployeeOptions ? 'Loading employees…' : 'Select an employee'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="emp in employeeOptions"
                                                :key="emp.accID"
                                                :value="String(emp.accID)"
                                            >
                                                {{ emp.fullName }}
                                            </SelectItem>
                                            <div v-if="!loadingEmployeeOptions && employeeOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No employees found.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <template #itemCode>
                            <FormField v-slot="{ componentField }" name="itemCode">
                                <FormItem>
                                    <FormLabel>Item Code</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingItemOptions ? 'Loading items…' : 'Select an item'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="item in itemOptions"
                                                :key="item['AC_COA_Cir_04-08']"
                                                :value="item['AC_COA_Cir_04-08']"
                                            >
                                                {{ item['AC_Name(Old)'] ?? item['AC_name(New)'] }} ({{ item['AC_COA_Cir_04-08'] }})
                                            </SelectItem>
                                            <div v-if="!loadingItemOptions && itemOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No inventory items found.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

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
                :title="`Delete ${baseentityname}`"
                :description="`Are you sure you want to delete this accountability record? This action cannot be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>