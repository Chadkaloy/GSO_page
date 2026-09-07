<script setup lang="ts">
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
const baseentityurl = '/Organization';
const baseentityname = 'Organization Member';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Organization Chart',
        href: baseentityurl,
    },
];

/* Interface matching database table structure */
export interface OrganizationMember {
    id: number;
    Name: string;
    Position: string;
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
const columns: ColumnDef<OrganizationMember>[] = [
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
        accessorKey: 'Name',
        header: ({ column }) => {
            return h(
                Button,
                { variant: 'ghost', onClick: () => column.toggleSorting(column.getIsSorted() === 'asc') },
                () => ['Full Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })]
            );
        },
        /* Upgraded text classes to explicitly support high-contrast display in dark themes */
        cell: ({ row }) => h('div', { class: 'font-semibold text-slate-900 dark:text-slate-100 break-words whitespace-normal' }, row.getValue('Name')),
    },
    {
        accessorKey: 'Position',
        header: ({ column }) => {
            return h(
                Button,
                { variant: 'ghost', onClick: () => column.toggleSorting(column.getIsSorted() === 'asc') },
                () => ['Official Position', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })]
            );
        },
        /* Upgraded text classes to explicitly support high-contrast display in dark themes */
        cell: ({ row }) => h('div', { class: 'text-slate-600 dark:text-slate-400 font-medium break-words whitespace-normal' }, row.getValue('Position')),
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

/* Form State Controllers */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

/* Zod Validation Schema mapped directly to database columns length specifications */
const schema = z.object({
    Name: z.string({ required_error: 'Member name is required' }).max(100, 'Name cannot exceed 100 characters'),
    Position: z.string({ required_error: 'Position placement designation is required' }).max(200, 'Position description cannot exceed 200 characters'),
});

const fieldconfig: any = {
    Name: {
        label: 'Personnel Full Name',
        inputProps: { placeholder: 'e.g. JOHN O. DOE' }
    },
    Position: {
        label: 'Official Designation Position',
        inputProps: { placeholder: 'e.g. Chief Supply Officer / Inventory Manager' }
    },
};

const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        Name: '',
        Position: '',
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
            toast.success(`${baseentityname} successfully registered to chart layout.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success(`${baseentityname} configurations securely saved.`);
        }

        resetForm();
        showDialogForm.value = false;
        await triggerTableRefresh();
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Validation parameters dropped by database limitations.');
        } else {
            toast.error('An unexpected runtime storage error occurred.');
        }
    }
};

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;
        const response = await axios.get(`${baseentityurl}/${id}`);
        form.setValues(response.data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error locating data identifier row:`, error);
        toast.error(`Unable to view targeted member configuration parameters.`);
    }
};

/* Delete Tracking System */
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
        toast.success(`${baseentityname} entry stripped from hierarchy records.`);
        showDeleteDialog.value = false;
        await triggerTableRefresh();
    } catch (error) {
        console.log(`Failed to process delete action:`, error);
        toast.error(`Database rejected request. Verify structural dependencies.`);
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600 text-white" @click="handleOpenDialogForm">
                        <Plus class="h-4 mr-1"></Plus> Add {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Register' : 'Modify' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Provide structural data metrics fitting chart layout rules. </DialogDescription>
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <DialogFooter class="pt-2">
                            <Button type="submit" class="bg-yellow-600 text-white w-full sm:w-auto">
                                {{ mode === 'create' ? 'Save Member' : 'Apply Updates' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Remove ${baseentityname}`"
                :description="`Are you certain you want to purge this staff record permanently from the visual hierarchy?`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>