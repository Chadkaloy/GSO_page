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
const baseentityurl = '/Inventory'; 
const baseentityname = 'Inventory Item'; 

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Inventory Dictionary',
        href: baseentityurl,
    },
];

/* Define Props Interface reflecting the exact database primary key and column naming conventions */
interface InventoryDictionary {
    Invent_ID: number; 
    'AC_COA_Cir_04-08': string;
    'AC_COA_Cir_015-09': string;
    'AC_Name(Old)': string;
    'AC_name(New)': string;
}

/* Auto-Refresh Reactivity Tracker Key */
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

/* Define Table Columns mapped directly to structural table structural keys */
const columns: ColumnDef<InventoryDictionary>[] = [
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
        accessorKey: 'AC_COA_Cir_04-08', 
        header: 'COA Cir 04-08 Code',
        cell: ({ row }) => h('div', { class: 'font-mono' }, row.getValue('AC_COA_Cir_04-08')),
    },
    {
        accessorKey: 'AC_COA_Cir_015-09', 
        header: 'COA Cir 015-09 Code',
        cell: ({ row }) => h('div', { class: 'font-mono' }, row.getValue('AC_COA_Cir_015-09')),
    },
    {
        accessorKey: 'AC_name(New)', 
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Account Name (New)', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words font-medium' }, row.getValue('AC_name(New)')),
    },
    {
        accessorKey: 'AC_Name(Old)', 
        header: 'Account Name (Old)',
        cell: ({ row }) => h('div', { class: 'break-words text-muted-foreground text-xs' }, row.getValue('AC_Name(Old)')),
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
                    onEdit: permissions.value.edit ? () => handleEdit(rowitem.Invent_ID) : undefined,
                    onDelete: permissions.value.delete ? () => openDeleteDialog(rowitem.Invent_ID) : undefined,
                }),
            );
        },
    },
];

/* Dialog Management States */
const showDialogForm = ref(false); 
const mode = ref('create'); 
const itemID = ref<number | null>(null); 

/* Schema constraints configured matching strict table schema constraints */
const schema = z.object({
    'AC_COA_Cir_04-08': z.string({ required_error: 'COA Cir 04-08 code is required' }).min(1).max(25),
    'AC_COA_Cir_015-09': z.string({ required_error: 'COA Cir 015-09 code is required' }).min(1).max(25),
    'AC_Name(Old)': z.string({ required_error: 'Old Account Name is required' }).min(2).max(100),
    'AC_name(New)': z.string({ required_error: 'New Account Name is required' }).min(2).max(100),
});

const fieldconfig: any = {
    'AC_COA_Cir_04-08': {
        label: 'COA Circular 2004-008 Code',
        inputProps: { placeholder: 'e.g., 1-07-07-010' },
    },
    'AC_COA_Cir_015-09': {
        label: 'COA Circular 2015-009 Code',
        inputProps: { placeholder: 'Enter new standardized asset code reference' },
    },
    'AC_Name(Old)': {
        label: 'Account Name (Old Designation Blueprint)',
        inputProps: { placeholder: 'Enter historic account definition title' },
    },
    'AC_name(New)': {
        label: 'Account Name (New Standardized Title)',
        inputProps: { placeholder: 'Enter active component definition heading' },
    },
};

const form = useForm({
    validationSchema: toTypedSchema(schema), 
    initialValues: {
        'AC_COA_Cir_04-08': '',
        'AC_COA_Cir_015-09': '',
        'AC_Name(Old)': '',
        'AC_name(New)': '',
    },
});

const resetForm = () => {
    form.resetForm(); 
    itemID.value = null; 
};

const handleOpenDialogForm = () => {
    resetForm();
    mode.value = 'create'; 
    showDialogForm.value = true; 
};

const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values); 
            toast.success(`${baseentityname} profile saved to catalog records.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values); 
            toast.success(`${baseentityname} records synchronized seamlessly.`);
        }

        resetForm(); 
        await triggerTableRefresh(); 
        showDialogForm.value = false; 
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors); 
            toast.error('Validation failure. Ensure codes remain entirely unique.');
        } else {
            toast.error('An unexpected database connection error occurred.');
        }
    }
};

const handleEdit = async (id: number) => {
    try {
        resetForm();
        mode.value = 'edit'; 
        itemID.value = id; 
        const response = await axios.get(`${baseentityurl}/${id}`); 
        form.setValues(response.data); 
        showDialogForm.value = true; 
    } catch (error) {
        toast.error(`Failed to gather targeted ${baseentityname} profile records.`);
    }
};

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
        toast.success(`${baseentityname} dropped from internal dictionary catalog lists.`);
        await triggerTableRefresh(); 
        showDeleteDialog.value = false; 
    } catch (error) {
        toast.error(`Unable to delete targeted structural item reference parameters.`);
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
                        <Plus class="h-4"></Plus> Create {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <ReusableDataTable
                :key="refreshKey"
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[475px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Register' : 'Modify Structural' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Maintain and record COA classification items for general asset ledger management.
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
                                {{ mode === 'create' ? 'Commit Records' : 'Apply Structural Transformations' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Drop ${baseentityname} Registration Mapping?`"
                :description="`Confirm permanent deletion of classification account parameters from your dictionary listings?`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>