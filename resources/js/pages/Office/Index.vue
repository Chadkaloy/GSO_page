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
const baseentityurl = '/Office'; 
const baseentityname = 'Office'; 

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/* Define Props Interface reflecting the Database Schema */
export interface OfficeDictionary {
    id: number; 
    officeName: string;
    officeCode: string;
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

/* Define Table Columns mapped directly to the database structural keys */
const columns: ColumnDef<OfficeDictionary>[] = [
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
        accessorKey: 'officeName', 
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Office Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal font-medium' }, row.getValue('officeName')),
    },
    {
        accessorKey: 'officeCode',
        header: 'Office Code',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal font-mono' }, row.getValue('officeCode')),
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

/* Schema rules strict matching column restrictions (VARCHAR lengths) */
const schema = z.object({
    officeName: z.string({ required_error: 'Office name is required' }).min(2).max(50),
    officeCode: z.string({ required_error: 'Office code is required' }).min(2).max(100),
});

const fieldconfig: any = {
    officeName: {
        label: 'Office Name',
        inputProps: { type: 'text', placeholder: 'Enter complete office designation' },
    },
    officeCode: {
        label: 'Office Code / Abbreviation',
        inputProps: { type: 'text', placeholder: 'e.g., PGO-GSO' },
    },
};

const form = useForm({
    validationSchema: toTypedSchema(schema), 
    initialValues: {
        officeName: '',
        officeCode: '',
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
            toast.success(`${baseentityname} profile saved cleanly.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values); 
            toast.success(`${baseentityname} records updated successfully.`);
        }

        resetForm(); 
        await triggerTableRefresh(); // FIXED: Re-renders data layout accurately instantly
        showDialogForm.value = false; 
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors); 
            toast.error('Validation mismatch. Check unique data parameters or field lengths.');
        } else {
            toast.error('An unexpected server error occurred.');
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
        toast.error(`Failed to pull structural ${baseentityname} dataset information.`);
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
        toast.success(`${baseentityname} reference purged from dictionary records.`);
        await triggerTableRefresh(); // FIXED: Re-renders data layout accurately instantly
        showDeleteDialog.value = false; 
    } catch (error) {
        toast.error(`Failed to execute structural drop instructions on ${baseentityname}.`);
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
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Register' : 'Modify Details' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Provide management details for the official administration office records below.
                    </DialogDescription>
                    <AutoForm
                        class="space-y-6"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
                        <DialogFooter>
                            <Button type="submit" class="bg-yellow-600 w-full">
                                {{ mode === 'create' ? 'Save Record Data' : 'Apply Structural Updates' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Drop ${baseentityname} Permanent Reference?`"
                :description="`Confirm structural record drop sequence? This action cannot be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>