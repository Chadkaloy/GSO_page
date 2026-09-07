<script setup lang="ts">
/* Import Components */
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import LocationCombobox from '@/components/entitycomponents/LocationCombobox.vue';
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
const baseentityurl = '/PropertyReturnSlip';
const baseentityname = 'Property Return Slip';

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/* Interface matching database columns explicitly */
export interface PropertyReturnSlip {
    id: number;
    LGU_Name: string;
    PurposeID: number;
    Qty: number;
    Unit: string;
    Descrp: string;
    Serial_Num: string;
    Prop_Number: string;
    ParNo: string;
    Name_of_Enduser: string;
    Unit_Value: number;
    Total_Value: number;
    Status: string;
    ReceiveBy_Name: string;
    ReceiveBy_Position: string;
    ReceiveBy_Date: string;
    ReceiveFrom_Name: string;
    ReceiveFrom_Position: string;
    ReceiveFrom_Date: string;
    purpose?: {
        ID: number;
        Purpose_Type: string;
    } | null;
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
const columns: ColumnDef<PropertyReturnSlip>[] = [
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
        accessorKey: 'ParNo',
        header: 'PAR No',
        cell: ({ row }) => h('div', { class: 'break-words font-mono font-bold' }, row.getValue('ParNo')),
    },
    {
        id: 'purpose_type',
        accessorFn: (row) => row.purpose?.Purpose_Type ?? null,
        header: 'Purpose',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.original.purpose?.Purpose_Type ?? '—'),
    },
    {
        accessorKey: 'LGU_Name',
        header: 'LGU Name',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('LGU_Name')),
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
        cell: ({ row }) => h('div', { class: 'text-center' }, row.getValue('Qty')),
    },
    {
        accessorKey: 'Unit',
        header: 'Unit',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Unit')),
    },
    {
        accessorKey: 'Prop_Number',
        header: 'Property No',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Prop_Number')),
    },
    {
        accessorKey: 'Name_of_Enduser',
        header: 'End User',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Name_of_Enduser')),
    },
    {
        accessorKey: 'Status',
        header: 'Status',
        cell: ({ row }) => h('div', { class: 'break-words font-medium text-blue-600' }, row.getValue('Status')),
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

/* Form Dialog and Refresh Pipelines */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

// Options for the "Purpose ID" dropdown — every purpose type, shown by its
// label while the value submitted is still the ID.
const purposeOptions = ref<{ ID: number; Purpose_Type: string }[]>([]);
const loadingPurposeOptions = ref(false);

const fetchPurposeOptions = async () => {
    loadingPurposeOptions.value = true;
    try {
        const response = await axios.get('/PrsPurposeDictionary/lookup');
        purposeOptions.value = response.data;
    } catch (error) {
        toast.error('Failed to load purpose type list.');
    } finally {
        loadingPurposeOptions.value = false;
    }
};

// Defensive fallback in case the linked purpose type was deleted after the
// fact — keeps the dropdown from going blank when editing that row.
const ensurePurposeInOptions = async (purposeID: number) => {
    if (!purposeID || purposeOptions.value.some((p) => p.ID === purposeID)) return;
    try {
        const response = await axios.get(`/PrsPurposeDictionary/${purposeID}`);
        purposeOptions.value = [
            { ID: response.data.ID, Purpose_Type: response.data.Purpose_Type },
            ...purposeOptions.value,
        ];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this purpose id.
    }
};

/* Schema constraints built to match database parameters */
const schema = z.object({
    LGU_Name: z.string({ required_error: 'LGU Name is required' }).max(250),
    PurposeID: z.coerce.number({ required_error: 'Purpose ID is required' }).int(),
    Qty: z.coerce.number({ required_error: 'Quantity is required' }).int(),
    Unit: z.string({ required_error: 'Unit is required' }).max(25),
    Descrp: z.string({ required_error: 'Description is required' }).max(200),
    Serial_Num: z.string({ required_error: 'Serial number is required' }).max(50),
    Prop_Number: z.string({ required_error: 'Property number is required' }).max(50),
    ParNo: z.string({ required_error: 'PAR No is required' }).max(50),
    Name_of_Enduser: z.string({ required_error: 'Enduser name is required' }).max(50),
    Unit_Value: z.coerce.number({ required_error: 'Unit value is required' }).int(),
    Total_Value: z.coerce.number({ required_error: 'Total value is required' }).int(),
    Status: z.enum(['In Progress', 'Complete', 'Cancelled'], { required_error: 'Status is required' }).default('In Progress'),
    ReceiveBy_Name: z.string({ required_error: 'Recipient name is required' }).max(50),
    ReceiveBy_Position: z.string({ required_error: 'Recipient position is required' }).max(50),
    ReceiveBy_Date: z.string({ required_error: 'Receipt date is required' }),
    ReceiveFrom_Name: z.string({ required_error: 'Sender name is required' }).max(50),
    ReceiveFrom_Position: z.string({ required_error: 'Sender position is required' }).max(50),
    ReceiveFrom_Date: z.string({ required_error: 'Sender date is required' }),
});

const fieldconfig: any = {
    LGU_Name: { label: 'LGU Name', inputProps: { placeholder: 'Enter LGU Name' } },
    PurposeID: { label: 'Purpose ID', inputProps: { type: 'number', placeholder: 'Enter Purpose Lookup ID' } },
    Qty: { label: 'Quantity', inputProps: { type: 'number' } },
    Unit: { label: 'Unit', inputProps: { placeholder: 'e.g., pcs, unit' } },
    Descrp: { label: 'Description', component: 'textarea', inputProps: { placeholder: 'Describe item conditions' } },
    Serial_Num: { label: 'Serial Number', inputProps: { placeholder: 'Enter serial number' } },
    Prop_Number: { label: 'Property Number', inputProps: { placeholder: 'Enter property number' } },
    ParNo: { label: 'PAR Number', inputProps: { placeholder: 'Enter original PAR number' } },
    Name_of_Enduser: { label: 'Name of End-User', inputProps: { placeholder: 'End-user name' } },
    Unit_Value: { label: 'Unit Value', inputProps: { type: 'number', placeholder: 'Cost per unit' } },
    Total_Value: { label: 'Total Value', inputProps: { type: 'number', placeholder: 'Total aggregated value' } },
    Status: { label: 'Status' },
    ReceiveBy_Name: { label: 'Received By (Name)', inputProps: { placeholder: 'Recipient full name' } },
    ReceiveBy_Position: { label: 'Received By (Position)', inputProps: { placeholder: 'Position title' } },
    ReceiveBy_Date: { label: 'Received By (Date)', inputProps: { type: 'date' } },
    ReceiveFrom_Name: { label: 'Received From (Name)', inputProps: { placeholder: 'Sender full name' } },
    ReceiveFrom_Position: { label: 'Received From (Position)', inputProps: { placeholder: 'Position title' } },
    ReceiveFrom_Date: { label: 'Received From (Date)', inputProps: { type: 'date' } },
};

const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        LGU_Name: '',
        PurposeID: 1,
        Qty: 1,
        Unit: '',
        Descrp: '',
        Serial_Num: '',
        Prop_Number: '',
        ParNo: '',
        Name_of_Enduser: '',
        Unit_Value: 0,
        Total_Value: 0,
        Status: 'In Progress',
        ReceiveBy_Name: '',
        ReceiveBy_Position: '',
        ReceiveBy_Date: '',
        ReceiveFrom_Name: '',
        ReceiveFrom_Position: '',
        ReceiveFrom_Date: '',
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
    fetchPurposeOptions();
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
            toast.error('Validation failed. Please verify form values.');
        } else {
            toast.error('An unexpected error occurred.');
        }
    }
};

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await fetchPurposeOptions();

        const response = await axios.get(`${baseentityurl}/${id}`);
        const data = response.data;

        await ensurePurposeInOptions(data.PurposeID);

        form.setValues(data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error fetching ${baseentityname} data:`, error);
        toast.error(`Failed to fetch ${baseentityname} data.`);
    }
};

/* Delete Dialog Pipelines */
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

            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[480px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Fill out all data parameters below to track returned property inventory. </DialogDescription>
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <template #PurposeID>
                            <FormField v-slot="{ componentField }" name="PurposeID">
                                <FormItem>
                                    <FormLabel>Purpose Type</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingPurposeOptions ? 'Loading purpose types…' : 'Select a purpose type'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="purpose in purposeOptions"
                                                :key="purpose.ID"
                                                :value="String(purpose.ID)"
                                            >
                                                {{ purpose.Purpose_Type }}
                                            </SelectItem>
                                            <div v-if="!loadingPurposeOptions && purposeOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No purpose types found.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <!-- Uses the location-autocomplete component so previously-typed
                             LGU names (cached via LocationController::remember() on the
                             backend) show up as suggestions. -->
                        <template #LGU_Name>
                            <FormField v-slot="{ componentField }" name="LGU_Name">
                                <FormItem>
                                    <FormLabel>LGU Name</FormLabel>
                                    <LocationCombobox
                                        :model-value="componentField.modelValue ?? ''"
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                        placeholder="Enter LGU Name"
                                    />
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

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Delete ${baseentityname}`"
                :description="`Are you sure you want to completely drop this property return slip? This transaction action cannot be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>