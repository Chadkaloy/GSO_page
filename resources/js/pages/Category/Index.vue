<script setup lang="ts">
/**
 * @file Category.vue
 * @description Vue 3 Single File Component (SFC) for managing Category records,
 *              featuring a TanStack-powered data table, dynamic modal forms via Zod and Vee-Validate,
 *              Axios CRUD operations, and Vue Sonner notification toasts.
 */

/* Import Components */
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue'; 
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue'; 
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import { AutoForm } from '@/components/ui/auto-form'; 
import { Button } from '@/components/ui/button'; 
import { Checkbox } from '@/components/ui/checkbox'; 
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'; 
import AppLayout from '@/layouts/AppLayout.vue'; 
import { Head } from '@inertiajs/vue3'; 

/* Import Utilities */
import { toTypedSchema } from '@vee-validate/zod'; 
import axios from 'axios'; 
import { ArrowUpDown, Plus } from 'lucide-vue-next'; 
import { useForm } from 'vee-validate'; 
import { h, ref } from 'vue'; 
import { toast } from 'vue-sonner'; 
import * as z from 'zod'; 

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table'; 

/* Import Types */
import { BreadcrumbItem } from '@/types'; 

/* Base Entity Configuration */
const baseentityurl = '/categories'; 
const baseentityname = 'Category'; 

/* Breadcrumbs definition for page header navigation */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/**
 * TypeScript interface matching the database structure for category items.
 */
export interface Category {
    id: number; 
    name: string; 
    description: string; 
}

/**
 * Definition of TanStack table columns including row selection, sorting, and cell templates.
 */
const columns: ColumnDef<Category>[] = [
    {
        id: 'select', 
        header: ({ table }) =>
            h(Checkbox, {
                modelValue:
                    table.getIsAllPageRowsSelected() ||
                    (table.getIsSomePageRowsSelected() && 'indeterminate'),
                'onUpdate:modelValue': (value) =>
                    table.toggleAllPageRowsSelected(!!value),
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
        accessorKey: 'name', 
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) =>
            h(
                'div',
                { class: 'break-words whitespace-normal' },
                row.getValue('name'),
            ),
    },
    {
        accessorKey: 'description', 
        header: 'Description',
        cell: ({ row }) =>
            h(
                'div',
                { class: 'break-words whitespace-normal' },
                row.getValue('description'),
            ),
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
                    onEdit: handleEdit, 
                    onDelete: openDeleteDialog, 
                }),
            );
        },
    },
];

/* Dialog State */
const showDialogForm = ref(false); 
const mode = ref('create'); 
const itemID = ref<number | null>(null); 

/**
 * Opens the creation dialog form and sets mode to create.
 */
const handleOpenDialogForm = () => {
    showDialogForm.value = true; 
    mode.value = 'create'; 
};

/**
 * Zod validation schema matching database constraints and field requirements.
 */
const schema = z.object({
    name: z
        .string({
            required_error: 'Name is required',
            invalid_type_error: 'Name must be a string',
        })
        .toUpperCase()
        .min(3, {
            message: 'Name must be at least 3 characters long',
        }),
    description: z.string().toUpperCase().optional(),
});

/**
 * Configuration mapping for AutoForm UI labels, input styles, and component types.
 */
const fieldconfig: any = {
    name: {
        label: 'Name',
        inputProps: {
            type: 'text',
            class: 'uppercase',
        },
        description: 'Name of the category',
    },
    description: {
        label: 'Description',
        component: 'textarea',
        inputProps: {
            class: 'uppercase',
            placeholder: 'Enter category description',
        },
    },
};

/* Vee-Validate form initialization with validation schema and initial default values */
const form = useForm({
    validationSchema: toTypedSchema(schema), 
    initialValues: {
        name: '',
        description: '',
    },
});

/**
 * Resets the form inputs and clears the active editing item ID.
 */
const resetForm = () => {
    form.resetForm(); 
    itemID.value = null; 
};

/* Table Reference State */
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null); 

/**
 * Handles form submissions for both create and update endpoints via Axios.
 */
const onSubmit = async (values: any) =>{
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values); 
            toast.success(`${baseentityname} created successfully.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values); 
            toast.success(`${baseentityname} updated successfully.`);
        }

        resetForm(); 
        await tableRef.value?.fetchRows(); 
        showDialogForm.value = false; 
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
 * Fetches targeted category record data by ID and opens the dialog in edit mode.
 */
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

/* Delete Dialog States */
const showDeleteDialog = ref(false); 
const itemIDToDelete = ref<number | null>(null); 

/**
 * Opens the delete confirmation dialog for a specific item ID.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id; 
    showDeleteDialog.value = true; 
};

/**
 * Executes a delete request via Axios for the selected record and updates the table.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;

        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`); 
        toast.success(`${baseentityname} deleted successfully.`);
        await tableRef.value?.fetchRows(); 
        showDeleteDialog.value = false; 
    } catch (error) {
        console.log(`Error deleting ${baseentityname}:`, error);
        toast.error(`Failed to delete ${baseentityname}. Please try again.`);
    }
};
</script>

<template>
    <!-- Page Title -->
    <Head :title="baseentityname" />

    <!-- Layout Container -->
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <!-- Action Header Toolbar -->
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button class="bg-blue-600" @click="handleOpenDialogForm">
                        <Plus class="h-4"></Plus> Create {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <!-- Data Table Component -->
            <ReusableDataTable
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <!-- Create / Update Dialog Form Modal -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Use this form to edit the {{ baseentityname }} details.
                    </DialogDescription>
                    <AutoForm class="space-y-6" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <DialogFooter>
                            <Button type="submit" class="bg-yellow-600">
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
                :description="`Are you sure you want to delete this ${baseentityname}? This action cannot be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>