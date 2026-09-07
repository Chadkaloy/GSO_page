<script setup lang="ts">
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
const baseentityurl = '/PropertyReceipt';
const baseentityname = 'Property Accountability Receipt';

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/* Define Interface matching database columns exactly */
export interface PropertyAccountabilityReceipt {
    id: number;
    Qty: number;
    Unit: string;
    Descrp: string;
    PropNo: string;
    ReceivedFrom_Name: string;
    ReceivedFrom_Position: string;
    ReceivedFrom_Date: string;
    ReceivedBy_Name: string;
    ReceivedBy_Position: string;
    ReceivedBy_Date: string;
    PAR: string;
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
const columns: ColumnDef<PropertyAccountabilityReceipt>[] = [
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
        accessorKey: 'PAR',
        header: 'PAR No',
        cell: ({ row }) => h('div', { class: 'break-words font-mono font-bold' }, row.getValue('PAR')),
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
        accessorKey: 'PropNo',
        header: 'Property No',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('PropNo')),
    },
    {
        accessorKey: 'ReceivedFrom_Name',
        header: 'From (Name)',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('ReceivedFrom_Name')),
    },
    {
        accessorKey: 'ReceivedBy_Name',
        header: 'By (Name)',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('ReceivedBy_Name')),
    },
    {
        accessorKey: 'ReceivedBy_Date',
        header: 'Date Received',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal text-center' }, row.getValue('ReceivedBy_Date')),
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

/* Zod Form Schema matching validation parameters exactly */
const schema = z.object({
    Qty: z.coerce.number({ required_error: 'Quantity is required' }).int(),
    Unit: z.string({ required_error: 'Unit is required' }).max(25),
    Descrp: z.string({ required_error: 'Description is required' }).max(150),
    PropNo: z.string({ required_error: 'Property number is required' }).max(25),
    ReceivedFrom_Name: z.string({ required_error: 'Sender name is required' }).max(50),
    ReceivedFrom_Position: z.string({ required_error: 'Sender position is required' }).max(50),
    ReceivedFrom_Date: z.string({ required_error: 'Sender date is required' }),
    ReceivedBy_Name: z.string({ required_error: 'Recipient name is required' }).max(50),
    ReceivedBy_Position: z.string({ required_error: 'Recipient position is required' }).max(50),
    ReceivedBy_Date: z.string({ required_error: 'Recipient date is required' }),
    PAR: z.string({ required_error: 'PAR Number is required' }).max(12),
});

const fieldconfig: any = {
    PAR: { label: 'PAR Number', inputProps: { placeholder: 'Enter PAR No.' } },
    Qty: { label: 'Quantity', inputProps: { type: 'number', placeholder: 'Enter quantity' } },
    Unit: { label: 'Unit', inputProps: { placeholder: 'e.g. pc, unit' } },
    Descrp: { label: 'Description', component: 'textarea', inputProps: { placeholder: 'Item descriptive features' } },
    PropNo: { label: 'Property Number', inputProps: { placeholder: 'Enter property number' } },
    ReceivedFrom_Name: { label: 'Received From (Name)', inputProps: { placeholder: 'Name of sender' } },
    ReceivedFrom_Position: { label: 'Received From (Position)', inputProps: { placeholder: 'Position' } },
    ReceivedFrom_Date: { label: 'Received From (Date)', inputProps: { type: 'date' } },
    ReceivedBy_Name: { label: 'Received By (Name)', inputProps: { placeholder: 'Name of recipient' } },
    ReceivedBy_Position: { label: 'Received By (Position)', inputProps: { placeholder: 'Position' } },
    ReceivedBy_Date: { label: 'Received By (Date)', inputProps: { type: 'date' } },
};

const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        Qty: 1,
        Unit: '',
        Descrp: '',
        PropNo: '',
        ReceivedFrom_Name: '',
        ReceivedFrom_Position: '',
        ReceivedFrom_Date: '',
        ReceivedBy_Name: '',
        ReceivedBy_Position: '',
        ReceivedBy_Date: '',
        PAR: '',
    },
});

/* Form Layout Reset Operations */
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

/* Edit Records Pipeline */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;
        const response = await axios.get(`${baseentityurl}/${id}`);
        form.setValues(response.data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error fetching ${baseentityname} data:`, error);
        toast.error(`Failed to fetch ${baseentityname} data.`);
    }
};

/* Delete Dialog Confirmation Operations */
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
                <DialogContent class="sm:max-w-[450px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Complete details below to manage property accountability receipts. </DialogDescription>
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
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
                :description="`Are you sure you want to delete this property receipt record? This action cannot be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>