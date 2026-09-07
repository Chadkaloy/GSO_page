<script setup lang="ts">
/* Import Components */
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue'; // Dropdown for row actions (edit/delete)
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue'; // Table component for displaying data
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import { AutoForm } from '@/components/ui/auto-form'; // AutoForm component for form handling
import { Button } from '@/components/ui/button'; // Button component
import { Checkbox } from '@/components/ui/checkbox'; // Checkbox component for row selection
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'; // Dialog components for forms
import AppLayout from '@/layouts/AppLayout.vue'; // Layout component for the page
import { Head, usePage } from '@inertiajs/vue3'; 

/* Import Utilities */
import { toTypedSchema } from '@vee-validate/zod'; // Utility for converting Zod schemas to Vee-Validate schemas
import axios from 'axios'; // HTTP client for API requests
import { ArrowUpDown, Plus } from 'lucide-vue-next'; // Icons for UI
import { useForm } from 'vee-validate'; // Form validation library
import { computed, h, ref } from 'vue'; // Vue composition API utilities
import { toast } from 'vue-sonner'; // Toast notifications
import * as z from 'zod'; // Zod library for schema validation

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table'; // Type definitions for table columns

/* Import Types */
import { BreadcrumbItem } from '@/types'; // Type definition for breadcrumbs

/* Base Entity Configuration */
const baseentityurl = '/PrsPurposeDictionary'; 
const baseentityname = 'Purpose Type'; 

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/* Define Props */
export interface PrsPurposeDictionary {
    ID: number;            
    Purpose_Type: string;  
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
const columns: ColumnDef<PrsPurposeDictionary>[] = [
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
        accessorKey: 'Purpose_Type', 
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Purpose Type', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Purpose_Type')),
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
                    onEdit: permissions.value.edit ? () => handleEdit(rowitem.ID) : undefined,
                    onDelete: permissions.value.delete ? () => openDeleteDialog(rowitem.ID) : undefined,
                }),
            );
        },
    },
];

/* Dialog State */
const showDialogForm = ref(false); 
const mode = ref('create'); 
const itemID = ref<number | null>(null); 

/* FIX: Reactive key to force update the table instance */
const refreshKey = ref(0);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);

/* Form Schema and Configuration */
const schema = z.object({
    Purpose_Type: z
        .string({
            required_error: 'Purpose Type is required',
            invalid_type_error: 'Purpose Type must be a string',
        })
        .toUpperCase()
        .min(3, {
            message: 'Purpose Type must be at least 3 characters long',
        }),
});

const fieldconfig: any = {
    Purpose_Type: {
        label: 'Purpose Type',
        inputProps: {
            type: 'text',
            class: 'uppercase',
            placeholder: 'e.g., OFFICE SUPPLIES',
        },
        description: 'Name of the purpose category',
    },
};

const form = useForm({
    validationSchema: toTypedSchema(schema), 
    initialValues: {
        Purpose_Type: '', 
    },
});

/* Form Handlers */
const resetForm = () => {
    form.resetForm({
        values: {
            Purpose_Type: '',
        }
    }); 
    itemID.value = null; 
};

const handleOpenDialogForm = () => {
    resetForm(); 
    mode.value = 'create'; 
    showDialogForm.value = true; 
};

// FIX: Increments the refresh key AND explicitly refetches — relying on the
// :key remount alone was inconsistent about refreshing immediately.
const triggerTableRefresh = async () => {
    refreshKey.value++;

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
        await triggerTableRefresh(); // Force-refresh table data mapping
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

/* Edit Handler */
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

/* Delete Dialog State */
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
        await triggerTableRefresh(); // Force-refresh table data mapping
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
    
    <!-- Layout Wrapper -->
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            
            <!-- Create Button -->
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600" @click="handleOpenDialogForm"> 
                        <Plus class="h-4"></Plus> Create {{ baseentityname }} 
                    </Button>
                </div>
            </div>

            <!-- Table UI (FIXED: Bound to :key to refresh table view explicitly) -->
            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <!-- Dialog Modal Form -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Use this form to save details for the {{ baseentityname }}. </DialogDescription>
                    <AutoForm class="space-y-6" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <DialogFooter>
                            <Button type="submit" class="bg-yellow-600">
                                {{ mode === 'create' ? 'Create' : 'Update' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
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