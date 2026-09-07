<script setup lang="ts">
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
const baseentityurl = '/CustodianSlipDescrp';
const baseentityname = 'Custodian Slip Item Description';

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/* Interface exactly matching table fields */
export interface InventCustodianSlipDescrp {
    id: number;
    icsID: number;
    // Eager-loaded from the parent custodian slip relation.
    // NOTE: Laravel serializes the `custodianSlip()` relation as snake_case
    // ("custodian_slip") in JSON responses, so the key here must match.
    custodian_slip?: { id: number; Invent_Item_No: string } | null;
    Descrp: string;
    Invent_Item_No: string;
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
const columns: ColumnDef<InventCustodianSlipDescrp>[] = [
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
        id: 'parent_item_no',
        accessorFn: (row) => row.custodian_slip?.Invent_Item_No ?? null,
        header: 'Parent Inventory Item No.',
        cell: ({ row }) => h('div', { class: 'font-mono text-xs' }, row.original.custodian_slip?.Invent_Item_No ?? '—'),
    },
    {
        accessorKey: 'Invent_Item_No',
        header: 'Inventory Item No',
        cell: ({ row }) => h('div', { class: 'break-words font-medium' }, row.getValue('Invent_Item_No')),
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
                () => ['Description Specs', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('Descrp')),
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

/* Dialog Form State management */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0);

// Options for the "Parent ICS" dropdown — every custodian slip, shown by its
// Invent_Item_No (per the request to display the inventory item number
// instead of the raw ICS id) while the value submitted is still the id.
const custodianSlipOptions = ref<{ id: number; Invent_Item_No: string }[]>([]);
const loadingCustodianSlipOptions = ref(false);

const fetchCustodianSlipOptions = async () => {
    loadingCustodianSlipOptions.value = true;
    try {
        const response = await axios.get('/CustodianSlip/lookup');
        custodianSlipOptions.value = response.data;
    } catch (error) {
        toast.error('Failed to load parent custodian slip list.');
    } finally {
        loadingCustodianSlipOptions.value = false;
    }
};

// Defensive fallback in case a parent slip was deleted after the fact —
// keeps the dropdown from going blank when editing that row.
const ensureCustodianSlipInOptions = async (icsID: number) => {
    if (!icsID || custodianSlipOptions.value.some((s) => s.id === icsID)) return;
    try {
        const response = await axios.get(`/CustodianSlip/${icsID}`);
        custodianSlipOptions.value = [
            { id: response.data.id, Invent_Item_No: response.data.Invent_Item_No },
            ...custodianSlipOptions.value,
        ];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this parent slip.
    }
};

/* Schema enforcement strictly limited to VARCHAR(50) per database layout */
const schema = z.object({
    icsID: z.coerce.number({ required_error: 'Parent Inventory Custodian Slip ID is required' }).int(),
    Descrp: z.string({ required_error: 'Item description is required' }).max(50, { message: 'Max 50 characters allowed' }),
    Invent_Item_No: z.string({ required_error: 'Inventory control item number is required' }).max(50, { message: 'Max 50 characters allowed' }),
});

const fieldconfig: any = {
    Invent_Item_No: { label: 'Inventory Item Number', inputProps: { placeholder: 'Unique item code' } },
    Descrp: { label: 'Specific Description Parameters', component: 'textarea', inputProps: { placeholder: 'Enter short descriptive tags, colors, attributes' } },
};

const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        icsID: undefined as any,
        Descrp: '',
        Invent_Item_No: '',
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
    fetchCustodianSlipOptions();
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
            toast.success(`${baseentityname} stored into registry.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success(`${baseentityname} records synchronized.`);
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

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await fetchCustodianSlipOptions();

        const response = await axios.get(`${baseentityurl}/${id}`);
        const data = response.data;

        await ensureCustodianSlipInOptions(data.icsID);

        form.setValues(data);
        showDialogForm.value = true;
    } catch (error) {
        console.log(`Error identifying row payload:`, error);
        toast.error(`Unable to view targeted parameters configuration.`);
    }
};

/* Confirmation dialogues pipeline */
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
        toast.success(`${baseentityname} row deleted securely.`);
        showDeleteDialog.value = false;
        await triggerTableRefresh();
    } catch (error) {
        console.log(`Failed to process delete operation:`, error);
        toast.error(`Database rejected request due to active foreign key tracking dependencies.`);
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
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Add' : 'Edit' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Provide granular parameters for the parent Inventory Custodian Slip matching schema criteria. </DialogDescription>
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <template #icsID>
                            <FormField v-slot="{ componentField }" name="icsID">
                                <FormItem>
                                    <FormLabel>Parent ICS (Inventory Item No.)</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingCustodianSlipOptions ? 'Loading custodian slips…' : 'Select a custodian slip'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="slip in custodianSlipOptions"
                                                :key="slip.id"
                                                :value="String(slip.id)"
                                            >
                                                {{ slip.Invent_Item_No }}
                                            </SelectItem>
                                            <div v-if="!loadingCustodianSlipOptions && custodianSlipOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No custodian slips found.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <DialogFooter class="pt-2">
                            <Button type="submit" class="bg-yellow-600 w-full sm:w-auto">
                                {{ mode === 'create' ? 'Save' : 'Update' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Purge ${baseentityname}`"
                :description="`Are you certain you want to delete this configuration spec entry? The reference row mapping will be permanently removed.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>