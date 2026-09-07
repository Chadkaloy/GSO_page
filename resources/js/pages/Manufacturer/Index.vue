<script setup lang="ts">
/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue'; // Table component for displaying data
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue'; // Dropdown for row actions (edit/delete)
import { AutoForm } from '@/components/ui/auto-form'; // AutoForm component for form handling
import { Button } from '@/components/ui/button'; // Button component
import { Checkbox } from '@/components/ui/checkbox'; // Checkbox component for row selection
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'; // Dialog components for forms
import AppLayout from '@/layouts/AppLayout.vue'; // Layout component for the page
import { Head } from '@inertiajs/vue3'; // Head component for setting the page title

/* Import Utilities */
import { toTypedSchema } from '@vee-validate/zod'; // Utility for converting Zod schemas to Vee-Validate schemas
import axios from 'axios'; // HTTP client for API requests
import { ArrowUpDown, Plus } from 'lucide-vue-next'; // Icons for UI
import { useForm } from 'vee-validate'; // Form validation library
import { h, ref } from 'vue'; // Vue composition API utilities
import { toast } from 'vue-sonner'; // Toast notifications
import * as z from 'zod'; // Zod library for schema validation

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table'; // Type definitions for table columns

/* Import Types */
import { BreadcrumbItem } from '@/types'; // Type definition for breadcrumbs

/* Base Entity Configuration */
const baseentityurl = '/manufacturers'; // API endpoint for the entity
const baseentityname = 'Manufacturer'; // Name of the entity

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/* Define Props */
export interface Manufacturer {
    id: number; // Unique identifier for the category
    name: string;
    url: string;
    support_url: string;
    support_phone: string;
    support_email: string;
}

/* Define Table Columns */
const columns: ColumnDef<Manufacturer>[] = [
    {
        id: 'select', // Column for row selection
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
        enableSorting: false, // Disable sorting for this column
        enableHiding: false, // Disable hiding for this column
    },
    {
        accessorKey: 'name', // Column for category name
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
        accessorKey: 'url', // Column for category description
        header: 'URL',
        cell: ({ row }) =>
            h(
                'div',
                { class: 'break-words whitespace-normal' },
                row.getValue('url'),
            ),
    },
    {
        id: 'actions', // Column for row actions (edit/delete)
        enableHiding: false, // Disable hiding for this column
        cell: ({ row }) => {
            const rowitem = row.original;

            return h(
                'div',
                { class: 'relative' },
                h(ReusableDropDownAction, {
                    rowitem,
                    onEdit: handleEdit, // Edit handler
                    onDelete: openDeleteDialog, // Delete handler
                }),
            );
        },
    },
];

/* Dialog State */
const showDialogForm = ref(false); // State for showing/hiding the form dialog
const mode = ref('create'); // Mode for the form (create/edit)
const itemID = ref<number | null>(null); // ID of the item being edited

/* Form Schema and Configuration */
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
    url: z.string().url().optional(),
    support_url: z.string().url().optional(),
    support_phone: z.string().regex(/^\+63\d{10}$/, {
        message:
            'Support phone must start with +63 and be followed by 10 digits.',
    }),
    support_email: z.string().email(),
});

const fieldconfig: any = {
    name: {
        label: 'Name',
        inputProps: {
            type: 'text',
            class: 'uppercase',
        },
        description: 'Name of the manufacturer',
    },
    url: {
        label: 'URL',
        inputProps: {
            type: 'text',
            placeholder: 'Enter manufacturer url',
        },
    },
    support_url: {
        label: 'Support URL',
        inputProps: {
            type: 'text',
        },
    },
    support_phone: {
        label: 'Support Phone',
        inputProps: {
            type: 'text',
        },
    },
    support_email: {
        label: 'Support Email',
        inputProps: {
            type: 'email',
        },
    },
};

const form = useForm({
    validationSchema: toTypedSchema(schema), // Validation schema
    initialValues: {
        name: '',
        url: undefined,
        support_url: undefined,
        support_phone: '',
        support_email: '',
    },
});

/* Form Handlers */
const resetForm = () => {
    form.resetForm(); // Reset the form
    itemID.value = null; // Clear the item ID
};

const handleOpenDialogForm = () => {
    showDialogForm.value = true; // Show the form dialog
    mode.value = 'create'; // Set mode to create
};

const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null); // Reference to the table component

const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(`${baseentityurl}`, values); // Create a new category
            toast.success(`${baseentityname} created successfully.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values); // Update an existing category
            toast.success(`${baseentityname} updated successfully.`);
        }

        resetForm(); // Reset the form
        await tableRef.value?.fetchRows(); // Refresh the table data
        showDialogForm.value = false; // Hide the form dialog
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors); // Set validation errors
            toast.error('Validation failed. Please check your input.');
        } else {
            toast.error('An unexpected error occurred.');
        }
    }
};

/* Edit Handler */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit'; // Set mode to edit
        itemID.value = id; // Set the item ID
        const response = await axios.get(`${baseentityurl}/${id}`); // Fetch the item data
        form.setValues(response.data); // Populate the form with the item data
        showDialogForm.value = true; // Show the form dialog
    } catch (error) {
        console.log(`Error fetching ${baseentityname} data:`, error);
        toast.error(`Failed to fetch ${baseentityname} data.`);
    }
};

/* Delete Dialog State */
const showDeleteDialog = ref(false); // State for showing/hiding the delete confirmation dialog
const itemIDToDelete = ref<number | null>(null); // ID of the item to be deleted

const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id; // Set the item ID to delete
    showDeleteDialog.value = true; // Show the delete confirmation dialog
};

const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;

        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`); // Delete the item
        toast.success(`${baseentityname} deleted successfully.`);
        await tableRef.value?.fetchRows(); // Refresh the table data
        showDeleteDialog.value = false; // Hide the delete confirmation dialog
    } catch (error) {
        console.log(`Error deleting ${baseentityname}:`, error);
        toast.error(`Failed to delete ${baseentityname}. Please try again.`);
    }
};
</script>

<template>
    <!-- Page Title -->
    <Head :title="baseentityname" />
    <!-- Layout -->
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <!-- Create Button -->
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button class="bg-blue-600" @click="handleOpenDialogForm">
                        <Plus class="h-4"></Plus> Create {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <ReusableDataTable
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <!-- Dialog Form -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle
                            >{{ mode === 'create' ? 'Create' : 'Update' }}
                            {{ baseentityname }}</DialogTitle
                        >
                    </DialogHeader>
                    <DialogDescription>
                        Use this form to edit the {{ baseentityname }} details.
                    </DialogDescription>
                    <AutoForm
                        class="space-y-6"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
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
