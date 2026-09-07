<script setup lang="ts">
/**
 * @file BincardIssuedRecord.vue
 * @description Vue 3 Single File Component (SFC) for managing Bincard Issued records,
 *              featuring a TanStack-powered data table, dynamic modal forms via Zod and Vee-Validate,
 *              Axios CRUD operations, and Vue Sonner notification toasts.
 */

/* Import Components */
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { Head, usePage } from '@inertiajs/vue3';

/* Import Utilities */
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { ArrowUpDown, Check, ChevronsUpDown, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table';

/* Import Types */
import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/BincardIssued';
const baseentityname = 'Bincard Issued Record';

/* Breadcrumbs definition for page navigation header */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/**
 * TypeScript interface matching the database structure for bincard issued entries.
 */
export interface BincardIssuedRecord {
    id: number;
    ItemSetID?: string;
    itemCode: string;
    bin_ID: number;
    // Eager-loaded from BincardIssuedRecord::bincard(). Laravel keeps this
    // key as-is since "bincard" has no uppercase letters to convert.
    bincard?: { id: number; Descrp: string; Supplier: string; PoNo: string } | null;
    recpnt: string;
    issued_date: string;
    qty: number;
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
const columns: ColumnDef<BincardIssuedRecord>[] = [
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
        accessorKey: 'itemCode',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Item Code', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('itemCode')),
    },
    {
        accessorKey: 'ItemSetID',
        header: 'Item Set ID',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('ItemSetID') || 'N/A'),
    },
    {
        accessorKey: 'bin_ID',
        header: 'Bin ID',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal font-mono' }, row.getValue('bin_ID')),
    },
    {
        id: 'bincard_descrp',
        accessorFn: (row) => row.bincard?.Descrp ?? null,
        header: 'Bin Item',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.original.bincard?.Descrp ?? '—'),
    },
    {
        accessorKey: 'recpnt',
        header: 'Recipient',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('recpnt')),
    },
    {
        accessorKey: 'issued_date',
        header: 'Issued Date',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('issued_date')),
    },
    {
        accessorKey: 'qty',
        header: 'Quantity',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('qty')),
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

/* Dialog and Table States */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);
const refreshKey = ref(0); // Unique reactive state key to force reload tracking

// Bin ID combobox — a search-as-you-type suggestion list backed by
// /Bincard/lookup, which reuses HasRelevanceSearch so the closest match
// (exact -> starts-with -> contains) rises to the top of the suggestions.
const binComboboxOpen = ref(false);
const binSearchQuery = ref('');
const binOptions = ref<{ id: number; Descrp: string; Supplier: string; PoNo: string; Balance: number }[]>([]);
const loadingBinOptions = ref(false);
let binSearchTimeout: ReturnType<typeof setTimeout> | null = null;

const fetchBinOptions = async (search = '') => {
    loadingBinOptions.value = true;
    try {
        const response = await axios.get('/Bincard/lookup', { params: { search } });
        binOptions.value = response.data;
    } catch (error) {
        toast.error('Failed to load bin list.');
    } finally {
        loadingBinOptions.value = false;
    }
};

watch(binSearchQuery, (value) => {
    if (binSearchTimeout) clearTimeout(binSearchTimeout);
    binSearchTimeout = setTimeout(() => fetchBinOptions(value), 250);
});

// Defensive fallback in case the linked bin record was deleted after the
// fact — keeps the combobox from showing a blank label when editing that row.
const ensureBinInOptions = async (binID: number) => {
    if (!binID || binOptions.value.some((b) => b.id === binID)) return;
    try {
        const response = await axios.get(`/Bincard/${binID}`);
        binOptions.value = [
            { id: response.data.id, Descrp: response.data.Descrp, Supplier: response.data.Supplier, PoNo: response.data.PoNo, Balance: response.data.Balance },
            ...binOptions.value,
        ];
    } catch (error) {
        // Non-fatal: combobox just won't have a label for this bin id.
    }
};

const binLabel = (bin: { id: number; Descrp: string; Supplier: string } | undefined) =>
    bin ? `${bin.Descrp} — ${bin.Supplier} (Bin #${bin.id})` : '';

/**
 * Zod validation schema matching database parameters and field rules.
 */
const schema = z.object({
    ItemSetID: z.string().optional(),
    itemCode: z.string({ required_error: 'Item Code is required' }).min(1, { message: 'Item Code cannot be empty' }),
    bin_ID: z.coerce.number({ required_error: 'Bin ID is required' }),
    recpnt: z.string({ required_error: 'Recipient is required' }).min(1, { message: 'Recipient cannot be empty' }),
    issued_date: z.string({ required_error: 'Issued date is required' }),
    qty: z.coerce.number({ required_error: 'Quantity is required' }),
});

/**
 * Configuration mapping for AutoForm UI labels, placeholders, and input types.
 */
const fieldconfig: any = {
    itemCode: {
        label: 'Item Code',
        inputProps: { type: 'text', placeholder: 'Enter item code' },
    },
    ItemSetID: {
        label: 'Item Set ID',
        inputProps: { type: 'text', placeholder: 'Enter item set ID (optional)' },
    },
    bin_ID: {
        label: 'Bin ID',
        inputProps: { type: 'number', placeholder: 'Enter bin ID' },
    },
    recpnt: {
        label: 'Recipient',
        inputProps: { type: 'text', placeholder: 'Enter recipient name' },
    },
    issued_date: {
        label: 'Issued Date',
        inputProps: { type: 'date' },
    },
    qty: {
        label: 'Quantity',
        inputProps: { type: 'number', placeholder: 'Enter quantity' },
    },
};

/* Vee-Validate form initialization with default values and schema binding */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        ItemSetID: '',
        itemCode: '',
        bin_ID: 0,
        recpnt: '',
        issued_date: '',
        qty: 0,
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
 * Opens the creation dialog form and sets mode to create.
 */
const handleOpenDialogForm = () => {
    showDialogForm.value = true;
    mode.value = 'create';
    binSearchQuery.value = '';
    fetchBinOptions();
};

/**
 * Triggers a cache-bust refresh for the data table component.
 */
const triggerTableRefresh = async () => {
    refreshKey.value += 1; // Increment component key to force rebuild
    await nextTick();      // Wait for DOM tracking changes
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
        await triggerTableRefresh(); // Force robust reload pipeline execution

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

        binSearchQuery.value = '';
        await fetchBinOptions();

        const response = await axios.get(`${baseentityurl}/${id}`);
        const data = response.data;

        await ensureBinInOptions(data.bin_ID);

        form.setValues(data);
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
        await triggerTableRefresh(); // Force robust reload pipeline execution here too
        
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

            <!-- Attached binding :key directive to auto-bust rendering cache instantly -->
            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <!-- Create / Update Dialog Form Modal -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription> Use this form to manage the {{ baseentityname }} details. </DialogDescription>
                    <AutoForm class="space-y-6" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
                        <template #bin_ID>
                            <FormField v-slot="{ componentField }" name="bin_ID">
                                <FormItem class="flex flex-col">
                                    <FormLabel>Bin ID</FormLabel>
                                    <Popover v-model:open="binComboboxOpen">
                                        <PopoverTrigger as-child>
                                            <FormControl>
                                                <Button
                                                    variant="outline"
                                                    role="combobox"
                                                    :aria-expanded="binComboboxOpen"
                                                    class="justify-between font-normal"
                                                >
                                                    <span class="truncate">
                                                        {{
                                                            componentField.modelValue
                                                                ? binLabel(binOptions.find((b) => b.id === componentField.modelValue))
                                                                    || `Bin #${componentField.modelValue}`
                                                                : 'Search for a bin…'
                                                        }}
                                                    </span>
                                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                </Button>
                                            </FormControl>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-[--reka-popover-trigger-width] p-0">
                                            <Command :filter-function="(list: any[]) => list">
                                                <CommandInput
                                                    v-model="binSearchQuery"
                                                    placeholder="Type a supplier, description, or PO number…"
                                                />
                                                <CommandList>
                                                    <CommandEmpty>
                                                        {{ loadingBinOptions ? 'Searching…' : 'No matching bins found.' }}
                                                    </CommandEmpty>
                                                    <CommandGroup>
                                                        <CommandItem
                                                            v-for="bin in binOptions"
                                                            :key="bin.id"
                                                            :value="String(bin.id)"
                                                            @select="
                                                                () => {
                                                                    componentField['onUpdate:modelValue']?.(bin.id);
                                                                    binComboboxOpen = false;
                                                                }
                                                            "
                                                        >
                                                            <Check
                                                                :class="cn('mr-2 h-4 w-4', componentField.modelValue === bin.id ? 'opacity-100' : 'opacity-0')"
                                                            />
                                                            <div class="flex flex-col">
                                                                <span>{{ bin.Descrp }}</span>
                                                                <span class="text-xs text-muted-foreground">
                                                                    {{ bin.Supplier }} · PO {{ bin.PoNo }} · Bin #{{ bin.id }} · Balance: {{ bin.Balance }}
                                                                </span>
                                                            </div>
                                                        </CommandItem>
                                                    </CommandGroup>
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

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