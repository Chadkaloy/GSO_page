<script setup lang="ts">
/**
 * @file TripTicketApproval Index Component (`Index.vue`)
 * @description Vue 3 Single File Component (SFC) for tracking Trip Ticket Approval logs,
 *              featuring a TanStack-powered data table, dynamic modal form generation via Zod and Vee-Validate,
 *              Axios CRUD backend communication, and Vue Sonner toast notifications.
 */

/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
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
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
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
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { ArrowUpDown, Check, Circle, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table';
import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/TripTicketApproval';
const baseentityname = 'Trip Ticket Approval Log';

/**
 * Breadcrumbs definition array for layout page headers.
 */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/**
 * TypeScript Interface matching database columns exactly for approval audit logs.
 */
export interface TripTicketApprovalRecord {
    id: number;
    trip_id: number;
    // Eager-loaded from the trip relation.
    trip?: { id: number; trip_no: string } | null;
    approver_id: number;
    // Eager-loaded from the approver relation (EmpAccountsRecord). Backend only
    // needs to select accID + fullName for this page's purposes.
    approver?: { accID: number; fullName: string } | null;
    action: string;
    comments: string | null;
    action_date: string;
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
 * Definition of TanStack table columns configuring row selections, sortable actions, and custom status badge renderers.
 */
const columns: ColumnDef<TripTicketApprovalRecord>[] = [
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
        accessorKey: 'action',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Action Taken', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => {
            const actionText = row.getValue('action') as string;
            let badgeClass = 'px-2 py-0.5 rounded-full text-xs font-semibold ';
            if (actionText === 'Approved') badgeClass += 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            else if (actionText === 'Rejected') badgeClass += 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            else badgeClass += 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200';
            
            return h('span', { class: badgeClass }, actionText);
        },
    },
    {
        id: 'trip_no',
        accessorFn: (row) => row.trip?.trip_no ?? null,
        header: 'Trip No.',
        cell: ({ row }) => h('div', { class: 'font-mono text-xs' }, row.original.trip?.trip_no ?? '—'),
    },
    {
        id: 'approver_name',
        // Confirmed via EmpAccountsRecord.php: the display-name column is fullName.
        accessorFn: (row) => row.approver?.fullName ?? null,
        header: 'Approved By',
        cell: ({ row }) => {
            const label = row.original.approver?.fullName ?? `Account #${row.original.approver_id}`;
            return h('div', { class: 'text-xs' }, label);
        },
    },
    {
        accessorKey: 'comments',
        header: 'Comments / Remarks',
        cell: ({ row }) => h('div', { class: 'break-words whitespace-normal text-slate-600 dark:text-slate-400' }, row.getValue('comments') || '—'),
    },
    {
        accessorKey: 'action_date',
        header: 'Action Date',
        cell: ({ row }) => h('div', {}, new Date(row.getValue('action_date')).toLocaleDateString()),
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const rowitem = row.original;
            const buttons = [];

            if (permissions.value.create) {
                buttons.push(
                    h(
                        Button,
                        {
                            size: 'icon',
                            variant: 'outline',
                            class: 'h-7 w-7 border-green-600 text-green-600 hover:bg-green-600 hover:text-white',
                            title: 'Mark Approved',
                            onClick: () => handleQuickAction(rowitem.id, 'Approved'),
                        },
                        () => h(Check, { class: 'h-3.5 w-3.5' }),
                    ),
                    h(
                        Button,
                        {
                            size: 'icon',
                            variant: 'outline',
                            class: 'h-7 w-7 border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white',
                            title: 'Mark Completed',
                            onClick: () => handleQuickAction(rowitem.id, 'Completed'),
                        },
                        () => h(Circle, { class: 'h-3.5 w-3.5' }),
                    ),
                    h(
                        Button,
                        {
                            size: 'icon',
                            variant: 'outline',
                            class: 'h-7 w-7 border-red-600 text-red-600 hover:bg-red-600 hover:text-white',
                            title: 'Mark Rejected',
                            onClick: () => handleQuickAction(rowitem.id, 'Rejected'),
                        },
                        () => h(X, { class: 'h-3.5 w-3.5' }),
                    ),
                );
            }

            if (permissions.value.edit) {
                buttons.push(
                    h(
                        Button,
                        {
                            size: 'icon',
                            variant: 'ghost',
                            class: 'h-7 w-7 text-muted-foreground',
                            title: 'Edit comments / date / trip',
                            onClick: () => handleEdit(rowitem.id),
                        },
                        () => h(Pencil, { class: 'h-3.5 w-3.5' }),
                    ),
                );
            }

            if (permissions.value.delete) {
                buttons.push(
                    h(
                        Button,
                        {
                            size: 'icon',
                            variant: 'ghost',
                            class: 'h-7 w-7 text-muted-foreground hover:text-red-600',
                            title: 'Delete',
                            onClick: () => openDeleteDialog(rowitem.id),
                        },
                        () => h(Trash2, { class: 'h-3.5 w-3.5' }),
                    ),
                );
            }

            return h('div', { class: 'flex items-center gap-1' }, buttons);
        },
    },
];

/* Dialog State Management */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);
const refreshKey = ref(0);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);

// Trip options depend on which decision is being made:
//  - Approved / Rejected require the trip to currently be Pending
//  - Completed requires the trip to currently be Approved (it has to have
//    been approved and actually happened before it can be marked done)
const pendingTrips = ref<{ id: number; trip_no: string }[]>([]);
const approvedTrips = ref<{ id: number; trip_no: string }[]>([]);
const loadingPendingTrips = ref(false);
const loadingApprovedTrips = ref(false);

const fetchPendingTrips = async () => {
    loadingPendingTrips.value = true;
    try {
        const response = await axios.get('/TripTicket/pending');
        pendingTrips.value = response.data;
    } catch (error) {
        toast.error('Failed to load pending trip list.');
    } finally {
        loadingPendingTrips.value = false;
    }
};

const fetchApprovedTrips = async () => {
    loadingApprovedTrips.value = true;
    try {
        const response = await axios.get('/TripTicket/approved');
        approvedTrips.value = response.data;
    } catch (error) {
        toast.error('Failed to load approved trip list.');
    } finally {
        loadingApprovedTrips.value = false;
    }
};

// Fallback source for edit mode: when editing an existing log, its trip's
// *current* status may not match either list above (e.g. editing a Rejected
// log — that trip is now Cancelled, not Pending or Approved). Fetched
// on-demand and kept separate so it doesn't pollute the two real option lists.
const editModeTripFallback = ref<{ id: number; trip_no: string } | null>(null);

const ensureTripInOptions = async (tripId: number) => {
    if (!tripId) return;
    if (pendingTrips.value.some((t) => t.id === tripId) || approvedTrips.value.some((t) => t.id === tripId)) return;
    try {
        const response = await axios.get(`/TripTicket/${tripId}`);
        editModeTripFallback.value = { id: response.data.id, trip_no: response.data.trip_no };
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this trip.
    }
};

const tripOptions = computed(() => {
    const base = form.values.action === 'Completed' ? approvedTrips.value : pendingTrips.value;
    const currentTripId = Number(form.values.trip_id) || null;
    const combined = [...base];

    if (
        currentTripId &&
        !combined.some((t) => t.id === currentTripId) &&
        editModeTripFallback.value?.id === currentTripId
    ) {
        combined.unshift(editModeTripFallback.value);
    }

    return combined.sort((a, b) => a.trip_no.localeCompare(b.trip_no));
});

// If the selected Decision changes such that the currently picked trip no
// longer belongs to the relevant list (e.g. switching from Approved to
// Completed), clear the stale selection rather than let it silently submit
// a mismatched trip/action pair.
watch(
    () => form.values.action,
    () => {
        const currentId = Number(form.values.trip_id) || null;
        if (currentId && !tripOptions.value.some((t) => t.id === currentId)) {
            form.setFieldValue('trip_id', undefined as any);
        }
    },
);

/**
 * Triggers re-rendering of the data table AND explicitly refetches — relying
 * on the :key remount alone was inconsistent about refreshing immediately.
 */
const triggerTableRefresh = async () => {
    refreshKey.value += 1;

    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

/**
 * Zod validation schema ensuring data integrity according to database limits and requirements.
 */
const schema = z.object({
    trip_id: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Trip Record ID is required' }).int().positive()),
    // 'Pending' removed — this form always represents someone actively
    // deciding. Completed added: once a trip is Approved and the actual
    // trip has happened, it can be marked Completed here too.
    action: z.enum(['Approved', 'Rejected', 'Completed'], { required_error: 'Action choice selection is required' }),
    comments: z.string().optional().nullable(),
    action_date: z.string({ required_error: 'Action timeline execution date is required' }),
});

/**
 * Configuration mapping for AutoForm UI layout labels, components, and input attributes.
 */
const fieldconfig: any = {
    comments: {
        label: 'Log Remarks / Decision Comments',
        inputProps: { type: 'textarea', placeholder: 'Explain approval/rejection contextual background details...' },
    },
    action_date: {
        label: 'Action Date & Time',
        inputProps: { type: 'datetime-local' },
    },
};

/**
 * Vee-Validate form control hook initialized with schema bindings and default state values.
 */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        trip_id: '' as any,
        action: 'Approved',
        comments: '',
        action_date: new Date().toISOString().slice(0, 16),
    },
});

/**
 * Resets form validations, inputs, and clears active item IDs.
 */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

/**
 * Opens the creation modal dialog in 'create' mode.
 */
const handleOpenDialogForm = () => {
    resetForm();
    mode.value = 'create';
    showDialogForm.value = true;
    fetchPendingTrips();
    fetchApprovedTrips();
};

/**
 * Submits form payloads via Axios for creating or updating approval log records.
 */
const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(baseentityurl, values);
            toast.success('Trip log authorization status verified successfully.');
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success('Authorization log notes updated.');
        }

        resetForm();
        await triggerTableRefresh();
        showDialogForm.value = false;
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            const firstError = Object.values(error.response.data.errors || {})[0] as string[] | undefined;
            toast.error(firstError?.[0] ?? 'Validation failure. Confirm table columns definitions requirements.');
        } else {
            toast.error('Service layer processing error occurred.');
        }
    }
};

/**
 * Fetches record details by ID, adjusts date format for inputs, and opens the modal in 'edit' mode.
 */
/**
 * Directly changes an existing log's decision from the table row buttons
 * (✓ Approved / O Completed / X Rejected), bypassing the edit dialog
 * entirely. Backend still enforces the full transition rules (trip must be
 * in the right prior state, full passenger manifest required for Approved,
 * etc.) — this just skips straight to submitting the decision.
 */
const handleQuickAction = async (id: number, action: 'Approved' | 'Completed' | 'Rejected') => {
    try {
        await axios.put(`${baseentityurl}/${id}`, {
            action,
            action_date: new Date().toISOString().slice(0, 16),
        });
        toast.success(`Marked as ${action}.`);
        await triggerTableRefresh();
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            const firstError = Object.values(error.response.data.errors || {})[0] as string[] | undefined;
            toast.error(firstError?.[0] ?? 'Failed to update decision.');
        } else {
            toast.error('Failed to update decision.');
        }
    }
};

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await Promise.all([fetchPendingTrips(), fetchApprovedTrips()]);

        const response = await axios.get(`${baseentityurl}/${id}`);
        const data = response.data;

        await ensureTripInOptions(data.trip_id);

        form.setValues({
            trip_id: data.trip_id,
            action: data.action === 'Pending' ? 'Approved' : data.action,
            comments: data.comments || '',
            action_date: data.action_date ? data.action_date.slice(0, 16) : '',
        });
        showDialogForm.value = true;
    } catch (error) {
        toast.error('Failed to locate matching index identification value references.');
    }
};

/* Delete Confirmation Dialog State Management */
const showDeleteDialog = ref(false);
const itemIDToDelete = ref<number | null>(null);

/**
 * Opens the alert confirmation dialog for record deletion.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id;
    showDeleteDialog.value = true;
};

/**
 * Sends a DELETE request to backend services via Axios and refreshes the data table rows.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;
        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`);
        toast.success('Log trace successfully discarded.');
        await triggerTableRefresh();
        showDeleteDialog.value = false;
    } catch (error) {
        toast.error('Failed to clear desired item index trace.');
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            
            <!-- Toolbar Action Section -->
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600 hover:bg-blue-700 text-white" @click="handleOpenDialogForm">
                        <Plus class="h-4 w-4 mr-1" /> Create Approval Action
                    </Button>
                </div>
            </div>

            <!-- Reusable Data Table component with reactive refresh key -->
            <ReusableDataTable
                :key="refreshKey"
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <!-- Create / Update Dialog Form Modal Component -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[450px]">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Log an official tracking resolution statement onto this system trip ledger history record.
                    </DialogDescription>
                    
                    <AutoForm
                        class="space-y-4 pt-2"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
                        <template #trip_id>
                            <FormField v-slot="{ componentField }" name="trip_id">
                                <FormItem>
                                    <FormLabel>
                                        Trip ({{ form.values.action === 'Completed' ? 'Approved' : 'Pending' }})
                                    </FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue
                                                    :placeholder="
                                                        (form.values.action === 'Completed' ? loadingApprovedTrips : loadingPendingTrips)
                                                            ? 'Loading trips…'
                                                            : `Select ${form.values.action === 'Completed' ? 'an approved' : 'a pending'} trip`
                                                    "
                                                />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="trip in tripOptions"
                                                :key="trip.id"
                                                :value="String(trip.id)"
                                            >
                                                {{ trip.trip_no }}
                                            </SelectItem>
                                            <div v-if="tripOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No {{ form.values.action === 'Completed' ? 'approved' : 'pending' }} trips found.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <template #action>
                            <FormField v-slot="{ componentField }" name="action">
                                <FormItem v-if="mode === 'create'">
                                    <FormLabel>Decision</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a decision" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="Approved">Approved</SelectItem>
                                            <SelectItem value="Rejected">Rejected</SelectItem>
                                            <SelectItem value="Completed">Completed</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                                <!-- Edit mode: no dropdown here — the decision itself is
                                     changed via the ✓ / O / X buttons on the table row,
                                     not through this form. Editing only covers
                                     comments/date/trip below. -->
                                <FormItem v-else>
                                    <FormLabel>Decision</FormLabel>
                                    <div class="rounded-md border px-3 py-2 text-sm text-muted-foreground">
                                        Currently <span class="font-medium text-foreground">{{ form.values.action }}</span>.
                                        Use the ✓ / O / X buttons on the table row to change the decision itself.
                                    </div>
                                </FormItem>
                            </FormField>
                        </template>

                        <DialogFooter class="pt-4 gap-2">
                            <Button type="button" variant="outline" @click="showDialogForm = false">Cancel</Button>
                            <Button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white">
                                {{ mode === 'create' ? 'Submit Record' : 'Save Changes' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Permanent Deletion Confirmation Alert Dialog Component -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Delete Audit Trace Record?`"
                :description="`Are you certain you want to purge this approval authorization trace index? History status metadata consistency might become corrupted.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>