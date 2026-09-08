<script setup lang="ts">
/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

/* Import Utilities */
import axios from 'axios';
import { ArrowUpDown, Plus } from 'lucide-vue-next';
import { computed, h, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table';
import { BreadcrumbItem } from '@/types';

const baseentityurl = '/FuelAllocation';
const baseentityname = 'Office Fuel Allocation';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

export interface FuelAllocationRecord {
    id: number;
    office_id: number;
    liters_allocated: number | string;
}

const permissions = computed(() => usePage().props.permissions as {
    create: boolean;
    edit: boolean;
    delete: boolean;
    view: boolean;
    print: boolean;
});

// --- Office options, fetched once from the existing /Office/list endpoint
// (the same one powering the Office Dictionary table itself — /Office/lookup
// doesn't actually exist in this app, unlike some other modules). Requesting
// a large per_page pulls the full list in one call instead of a 10-row page.
// The exact field names on each office row aren't confirmed here, so we
// defensively try a few common key names rather than assuming one — a
// missing label just shows as blank/"Office #id" instead of throwing and
// breaking the page. ---
const officeOptions = ref<{ id: number; label: string }[]>([]);
const loadingOffices = ref(false);

const resolveOfficeLabel = (raw: any): string => {
    return raw.office_name ?? raw.officeName ?? raw.label ?? raw.name ?? raw.text ?? `Office #${raw.id ?? raw.value}`;
};

const fetchOfficeOptions = async () => {
    loadingOffices.value = true;
    try {
        const response = await axios.post('/Office/list', { per_page: 1000 });
        const rawList: any[] = Array.isArray(response.data) ? response.data : (response.data?.data ?? []);
        officeOptions.value = rawList.map((raw) => ({
            id: raw.id ?? raw.value,
            label: resolveOfficeLabel(raw),
        }));
    } catch (error) {
        console.error(error);
        toast.error('Failed to load office list.');
    } finally {
        loadingOffices.value = false;
    }
};

const officeLabelMap = computed(() => {
    const map = new Map<number, string>();
    officeOptions.value.forEach((o) => map.set(o.id, o.label));
    return map;
});

const officeLabel = (officeId: number) => officeLabelMap.value.get(officeId) ?? `Office #${officeId}`;

/* Table Columns */
const columns: ColumnDef<FuelAllocationRecord>[] = [
    {
        id: 'office',
        accessorFn: (row) => officeLabel(row.office_id),
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Office', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'font-medium' }, officeLabel(row.original.office_id)),
    },
    {
        accessorKey: 'liters_allocated',
        header: 'Liters Allocated',
        cell: ({ row }) => h('div', {}, `${row.getValue('liters_allocated')} L`),
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

/* Reactive State */
const showDialogForm = ref(false);
const mode = ref<'create' | 'edit'>('create');
const itemID = ref<number | null>(null);
const submitting = ref(false);

const formOfficeId = ref<number | undefined>(undefined);
const formLitersAllocated = ref<string>('');
const formErrors = ref<Record<string, string>>({});

const refreshKey = ref(0);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);

const triggerTableRefresh = async () => {
    refreshKey.value += 1;
    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

const resetForm = () => {
    formOfficeId.value = undefined;
    formLitersAllocated.value = '';
    formErrors.value = {};
    itemID.value = null;
};

const handleOpenDialogForm = () => {
    resetForm();
    mode.value = 'create';
    showDialogForm.value = true;
};

const onSubmit = async () => {
    formErrors.value = {};

    if (!formOfficeId.value) {
        formErrors.value['office_id'] = 'Please select an office.';
        toast.error('Please select an office.');
        return;
    }
    const litersNumber = Number(formLitersAllocated.value);
    if (formLitersAllocated.value === '' || isNaN(litersNumber) || litersNumber < 0) {
        formErrors.value['liters_allocated'] = 'Enter a valid, non-negative number of liters.';
        toast.error('Enter a valid, non-negative number of liters.');
        return;
    }

    submitting.value = true;
    try {
        const payload = {
            office_id: formOfficeId.value,
            liters_allocated: litersNumber,
        };

        if (mode.value === 'create') {
            await axios.post(baseentityurl, payload);
            toast.success('Fuel allocation added successfully.');
        } else {
            await axios.put(`${baseentityurl}/${itemID.value}`, payload);
            toast.success('Fuel allocation updated successfully.');
        }

        resetForm();
        await triggerTableRefresh();
        showDialogForm.value = false;
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors || {};
            formErrors.value = Object.fromEntries(
                Object.entries(errors).map(([key, msgs]) => [key, (msgs as string[])[0]]),
            );
            const firstError = Object.values(errors)[0] as string[] | undefined;
            toast.error(firstError?.[0] ?? 'Validation failed. Please check the fields.');
        } else {
            console.error(error);
            toast.error('An unexpected error occurred.');
        }
    } finally {
        submitting.value = false;
    }
};

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        const response = await axios.get(`${baseentityurl}/${id}`);
        formOfficeId.value = response.data.office_id;
        formLitersAllocated.value = String(response.data.liters_allocated);

        showDialogForm.value = true;
    } catch (error) {
        toast.error('Failed to load the requested fuel allocation record.');
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
        toast.success('Fuel allocation removed.');

        await triggerTableRefresh();
        showDeleteDialog.value = false;
    } catch (error) {
        toast.error('An error occurred while removing this allocation.');
    }
};

onMounted(fetchOfficeOptions);
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600 hover:bg-blue-700 text-white" @click="handleOpenDialogForm">
                        <Plus class="h-4 w-4 mr-1" /> Add Fuel Allocation
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
                <DialogContent class="sm:max-w-[420px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Add' : 'Modify' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Set how many liters of fuel are allocated to an office.
                    </DialogDescription>

                    <form class="space-y-4 pt-2" @submit.prevent="onSubmit">
                        <div class="space-y-2">
                            <Label>Office</Label>
                            <Select
                                :model-value="formOfficeId ? String(formOfficeId) : undefined"
                                @update:model-value="(val) => (formOfficeId = val ? Number(val) : undefined)"
                            >
                                <SelectTrigger>
                                    <SelectValue :placeholder="loadingOffices ? 'Loading offices…' : 'Select an office'" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="office in officeOptions"
                                        :key="office.id"
                                        :value="String(office.id)"
                                    >
                                        {{ office.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="formErrors['office_id']" class="text-sm font-medium text-destructive">
                                {{ formErrors['office_id'] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>Liters Allocated</Label>
                            <Input
                                v-model="formLitersAllocated"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="e.g., 50"
                            />
                            <p v-if="formErrors['liters_allocated']" class="text-sm font-medium text-destructive">
                                {{ formErrors['liters_allocated'] }}
                            </p>
                        </div>

                        <DialogFooter class="pt-4 gap-2">
                            <Button type="button" variant="outline" @click="showDialogForm = false">Cancel</Button>
                            <Button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="submitting">
                                {{ submitting ? 'Saving…' : 'Save Record' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Remove Fuel Allocation?`"
                :description="`Are you sure you want to remove this office's fuel allocation record? This can't be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>