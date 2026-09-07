<script setup lang="ts">
/**
 * @file Employee.vue
 * @description Vue 3 Single File Component (SFC) for managing Employee Account records,
 *              featuring a TanStack-powered data table, dynamic modal forms via Zod and Vee-Validate,
 *              multipart/form-data file upload support for profile images, Axios CRUD operations, 
 *              and Vue Sonner notification toasts.
 */

/* Import Components */
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue'; 
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue'; 
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import LocationCombobox from '@/components/entitycomponents/LocationCombobox.vue';
import { AutoForm } from '@/components/ui/auto-form'; 
import { Button } from '@/components/ui/button'; 
import { Checkbox } from '@/components/ui/checkbox'; 
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'; 
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue'; 
import { Head, usePage } from '@inertiajs/vue3'; 
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
const baseentityurl = '/Employee'; 
const baseentityname = 'Employee Account'; 

/**
 * Breadcrumbs definition for page header navigation.
 */
const breadcrumbs: BreadcrumbItem[] = [
    { title: baseentityname, href: baseentityurl },
];

/**
 * TypeScript interface explicitly mapping the employee account database structure.
 */
export interface EmployeeAccount {
    accID: number;
    user_id: number;
    // Eager-loaded from the linked login account (id + name + email only).
    user?: { id: number; name: string; email: string } | null;
    accLevel: string;
    fullName: string;
    Age: number;
    Gender: string;
    Address: string;
    Pos: string;
    Mobile: string;
    image?: string;
}

/* Cache Buster & Table Refresh State */
const refreshKey = ref(0);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);

/**
 * Triggers component re-render cache-bust AND explicitly refetches — relying
 * on the :key remount alone was inconsistent about refreshing immediately.
 */
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

/**
 * The logged-in user's own accLevel, read from the shared auth.user prop
 * (auth.user.employee_record.accLevel). Used to hide Edit/Delete on rows
 * that outrank the current user — mirrors the same rank check enforced
 * server-side in EmpAccountsRecordController::abortIfTargetOutranksActor().
 * Frontend-only enforcement is never sufficient on its own; this is purely
 * a UX nicety so people don't hit a 403 after filling out a form.
 */
const ACC_LEVEL_RANK: Record<string, number> = { E: 1, A: 2, S: 3 };

const currentUserAccLevel = computed(() => {
    const page = usePage().props as any;
    return page.auth?.user?.employee_record?.accLevel as string | undefined;
});

/**
 * True if the current user is allowed to edit/delete this specific row —
 * i.e. the row's accLevel does not outrank the current user's own.
 */
const canActOnRow = (rowAccLevel: string): boolean => {
    const actorRank = ACC_LEVEL_RANK[currentUserAccLevel.value ?? ''] ?? 0;
    const targetRank = ACC_LEVEL_RANK[rowAccLevel] ?? 0;
    return targetRank <= actorRank;
};

/**
 * Definition of TanStack table columns containing row selectors, sortable headers, and cell templates.
 */
const columns: ColumnDef<EmployeeAccount>[] = [
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
        accessorKey: 'fullName', 
        header: ({ column }) => h(Button, { variant: 'ghost', onClick: () => column.toggleSorting(column.getIsSorted() === 'asc') }, () => ['Full Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })]),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('fullName')),
    },
    { 
        id: 'linked_account',
        accessorFn: (row) => row.user?.email ?? null,
        header: 'Login Account',
        cell: ({ row }) => {
            const user = row.original.user;
            if (!user) return h('div', { class: 'text-muted-foreground text-xs' }, 'Not linked');
            return h('div', { class: 'text-xs' }, `${user.name} (${user.email})`);
        },
    },
    { accessorKey: 'Pos', header: 'Position' },
    {
        id: 'actions', 
        enableHiding: false, 
        cell: ({ row }) => {
            const rowitem = row.original;
            const allowed = permissions.value.edit && canActOnRow(rowitem.accLevel);
            const allowedDelete = permissions.value.delete && canActOnRow(rowitem.accLevel);
            return h('div', { class: 'relative' }, h(ReusableDropDownAction, {
                rowitem,
                onEdit: allowed ? () => handleEdit(rowitem.accID) : undefined,
                onDelete: allowedDelete ? () => openDeleteDialog(rowitem.accID) : undefined,
            }));
        },
    },
];

/* Dialog Form State Management */
const showDialogForm = ref(false); 
const mode = ref('create'); 
const itemID = ref<number | null>(null); 
const imageFile = ref<File | null>(null);

// Options for the "Login Account" dropdown — only users not already linked
// to an employee/role record (a user can only be linked once).
const availableUsers = ref<{ id: number; name: string; email: string }[]>([]);
const loadingAvailableUsers = ref(false);

const fetchAvailableUsers = async () => {
    loadingAvailableUsers.value = true;
    try {
        const response = await axios.get('/Employee/available-users');
        availableUsers.value = response.data;
    } catch (error) {
        toast.error('Failed to load available login accounts.');
    } finally {
        loadingAvailableUsers.value = false;
    }
};

// When editing an existing employee record, its linked user won't appear in
// availableUsers() (they're linked to *this* record, so they're correctly
// excluded from "available"). Fetch them specifically so the dropdown still
// shows their name instead of going blank.
const ensureUserInOptions = async (userId: number) => {
    if (!userId || availableUsers.value.some((u) => u.id === userId)) return;
    try {
        const response = await axios.get(`/Employee/users/${userId}`);
        availableUsers.value = [response.data, ...availableUsers.value];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this user.
    }
};

/**
 * Zod validation schema matching structural database column limits and input rules.
 */
const schema = z.object({
    user_id: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Linking to a login account is required' }).int().positive()),
    fullName: z.string({ required_error: 'Full name is required' }).min(2).max(25),
    accLevel: z.enum(['S', 'A', 'E'], { required_error: 'Access level is required' }),
    Age: z.number({ required_error: 'Age must be a number' }).min(18).max(100),
    Gender: z.enum(['Male', 'Female', 'Other'], { required_error: 'Gender is required' }),
    Pos: z.string({ required_error: 'Position layout title is required' }).max(25),
    Mobile: z.string({ required_error: 'Mobile phone number required' }).min(10).max(11),
    Address: z.string({ required_error: 'Address is required' }).max(150),
});

/**
 * Configuration mapping for AutoForm UI labels, input types, and placeholders.
 */
const fieldconfig: any = {
    // autocomplete left as a reminder comment: no longer needed here since
    // username/password/email fields were removed — login credentials live
    // on the users table now, this form only links to an existing account.
    fullName: { label: 'Full Name' },
    accLevel: { label: 'Access Level' },
    Age: { label: 'Age', inputProps: { type: 'number' } },
    Gender: { label: 'Gender' },
    Pos: { label: 'Job Position / Title' },
    Mobile: { label: 'Mobile Number' },
    Address: { label: 'Home Address', component: 'textarea' },
};

/* Vee-Validate form initialization with default values and schema binding */
const form = useForm({
    validationSchema: toTypedSchema(schema), 
    initialValues: {
        user_id: '' as any,
        fullName: '',
        accLevel: 'E',
        // ^ 'E' = Employee, the lowest-privilege default. See the #accLevel
        // custom slot below for the S/A/E -> Super Admin/Admin/Employee
        // label mapping (accLevel stays char(1) in the DB; only the UI
        // shows full words).
        Age: 18,
        Gender: 'Male',
        Pos: '',
        Mobile: '',
        Address: '',
    },
});

/**
 * Captures selected profile image files from the native file input element.
 */
const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        imageFile.value = target.files[0];
    }
};

/**
 * Resets the form inputs, clears file selections, and resets active editing states.
 */
const resetForm = () => {
    form.resetForm(); 
    itemID.value = null; 
    imageFile.value = null;
    const fileInput = document.getElementById('image-upload') as HTMLInputElement;
    if (fileInput) fileInput.value = '';
};

/**
 * Opens the creation dialog form in create mode with a blank state.
 */
const handleOpenDialogForm = () => {
    resetForm(); 
    mode.value = 'create'; 
    showDialogForm.value = true; 
    fetchAvailableUsers();
};

/**
 * Handles form submissions for creating or updating employee records via multipart FormData requests.
 */
const onSubmit = async (values: any) => {
    try {
        const formData = new FormData();
        
        Object.keys(values).forEach(key => {
            formData.append(key, values[key]);
        });

        if (imageFile.value) {
            formData.append('image', imageFile.value);
        }

        if (mode.value === 'create') {
            await axios.post(baseentityurl, formData, { headers: { 'Content-Type': 'multipart/form-data' } }); 
            toast.success(`${baseentityname} created successfully.`);
        } else if (mode.value === 'edit') {
            formData.append('_method', 'PUT');
            await axios.post(`${baseentityurl}/${itemID.value}`, formData, { headers: { 'Content-Type': 'multipart/form-data' } }); 
            toast.success(`${baseentityname} updated successfully.`);
        }

        resetForm(); 
        await triggerTableRefresh(); 
        showDialogForm.value = false; 
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors); 
            toast.error('Validation failed. Please check your inputs.');
        } else {
            toast.error('An unexpected error occurred.');
        }
    }
};

/**
 * Fetches an employee record by ID and opens the dialog modal in edit mode.
 */
const handleEdit = async (id: number) => {
    try {
        resetForm();
        mode.value = 'edit'; 
        itemID.value = id; 

        await fetchAvailableUsers();

        const response = await axios.get(`${baseentityurl}/${id}`); 
        const data = response.data;

        await ensureUserInOptions(data.user_id);

        form.setValues(data); 
        showDialogForm.value = true; 
    } catch (error) {
        toast.error(`Failed to fetch ${baseentityname} data.`);
    }
};

/* Delete Confirmation Dialog States */
const showDeleteDialog = ref(false); 
const itemIDToDelete = ref<number | null>(null); 

/**
 * Opens the alert dialog confirmation to delete an employee record.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id; 
    showDeleteDialog.value = true; 
};

/**
 * Sends a delete request via Axios for the selected record and refreshes the data table.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;
        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`); 
        toast.success(`${baseentityname} deleted successfully.`);
        await triggerTableRefresh(); 
        showDeleteDialog.value = false; 
    } catch (error) {
        toast.error(`Failed to delete ${baseentityname}.`);
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <!-- Action Header Toolbar -->
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600" @click="handleOpenDialogForm"> 
                        <Plus class="h-4"></Plus> Create {{ baseentityname }} 
                    </Button>
                </div>
            </div>

            <!-- Data Table Component with Key-Based Cache Buster -->
            <ReusableDataTable :key="refreshKey" ref="tableRef" :columns="columns" :baseentityname="baseentityname" :baseentityurl="baseentityurl" />

            <!-- Create / Update Dialog Modal Form -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[450px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    
                    <AutoForm class="space-y-4" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">

                        <template #user_id>
                            <FormField v-slot="{ componentField }" name="user_id">
                                <FormItem>
                                    <FormLabel>Login Account</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingAvailableUsers ? 'Loading accounts…' : 'Select a registered user'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="user in availableUsers"
                                                :key="user.id"
                                                :value="String(user.id)"
                                            >
                                                {{ user.name }} ({{ user.email }})
                                            </SelectItem>
                                            <div v-if="!loadingAvailableUsers && availableUsers.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No unlinked accounts available.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <!-- Access Level stays a single-char (S/A/E) value in the DB
                             (kept separate from the login account's real users.role,
                             per project decision) — this custom slot just shows full
                             words instead of the raw letters AutoForm would otherwise
                             render directly from the Zod enum. -->
                        <template #accLevel>
                            <FormField v-slot="{ componentField }" name="accLevel">
                                <FormItem>
                                    <FormLabel>Access Level</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue"
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select an access level" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="S">Super Admin</SelectItem>
                                            <SelectItem value="A">Admin</SelectItem>
                                            <SelectItem value="E">Employee</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <!-- Uses the location-autocomplete component so previously-typed
                             addresses (cached via LocationController::remember() on the
                             backend) show up as suggestions. multiline keeps it rendering
                             as a Textarea, matching the field's original 'textarea'
                             component config. -->
                        <template #Address>
                            <FormField v-slot="{ componentField }" name="Address">
                                <FormItem>
                                    <FormLabel>Home Address</FormLabel>
                                    <LocationCombobox
                                        :model-value="componentField.modelValue ?? ''"
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                        placeholder="Type an address…"
                                        :multiline="true"
                                    />
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>
                        <div class="space-y-2">
                            <Label for="image-upload" class="text-xs font-medium text-foreground">Profile Image Attachment</Label>
                            <Input id="image-upload" type="file" accept="image/*" @change="handleFileChange" class="cursor-pointer file:text-foreground" />
                        </div>

                        <DialogFooter class="pt-2">
                            <Button type="submit" class="bg-yellow-600 w-full">
                                {{ mode === 'create' ? 'Save Employee' : 'Update Profile' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Alert Dialog -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Delete ${baseentityname}`"
                :description="`Are you sure you want to completely drop this employee profile? This baseline security operation cannot be rolled back.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>