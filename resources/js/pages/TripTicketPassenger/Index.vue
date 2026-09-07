<script setup lang="ts">
/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
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
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import {
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/TripTicketPassenger';
const baseentityname = 'Trip Ticket Passenger';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/* TypeScript Interfaces matching the /TripTicketPassenger/grouped response */
export interface PassengerEntry {
    id: number;
    passenger_name: string;
    office: string | null;
    contact_no: string | null;
}

export interface GroupedTripRow {
    trip_id: number;
    trip_no: string;
    passengers: PassengerEntry[];
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

/* Reactive State Declarations */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);

// Options for the "Trip No." dropdown — only Pending trips, populated from
// GET /TripTicket/pending. Each trip now also carries its assigned vehicle's
// capacity (if any) and passengers_count so we can show/filter by seats left.
const pendingTrips = ref<{
    id: number;
    trip_no: string;
    vehicle_id: number | null;
    vehicle?: { id: number; plate_no: string; capacity: number } | null;
    passengers_count: number;
}[]>([]);
const loadingPendingTrips = ref(false);

// Tracks which trip this passenger was originally on when opening the edit
// dialog, so re-saving them on the same trip doesn't get blocked by their
// own existing seat counting against the total.
const originalTripId = ref<number | null>(null);

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

// When editing a passenger whose trip is no longer Pending (e.g. it has since
// been Approved/Completed), that trip won't be in the Pending-only dropdown
// list. Fetch it specifically and splice it in so the dropdown can still
// show its trip_no instead of going blank.
const ensureTripInOptions = async (tripId: number) => {
    if (!tripId || pendingTrips.value.some((t) => t.id === tripId)) return;
    try {
        const response = await axios.get(`/TripTicket/${tripId}`);
        pendingTrips.value = [response.data, ...pendingTrips.value];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this trip.
    }
};

// Trip options annotated with remaining seats and filtered down to only
// trips that still have room. A trip with no vehicle assigned yet has
// nothing to cap against, so it's always shown as available. The currently
// selected trip (edit mode) is always kept so the dropdown never goes blank.
const tripOptions = computed(() => {
    const currentTripId = Number(form.values.trip_id) || null;

    return pendingTrips.value
        .map((trip) => {
            const capacity = trip.vehicle?.capacity ?? null;
            // Editing an existing passenger on this same trip shouldn't count
            // their own current seat against the remaining total.
            const bookedSeats = trip.passengers_count - (originalTripId.value === trip.id ? 1 : 0);
            const remaining = capacity !== null ? capacity - bookedSeats : null;
            return { ...trip, capacity, remaining };
        })
        .filter((trip) => trip.remaining === null || trip.remaining > 0 || trip.id === currentTripId)
        .sort((a, b) => a.trip_no.localeCompare(b.trip_no));
});

// --- Grouped trip table state (one row per trip, passengers nested inside,
// expandable to reveal individual passenger edit/delete actions) ---
const groupedRows = ref<GroupedTripRow[]>([]);
const loadingRows = ref(false);
const searchText = ref('');
const currentPage = ref(1);
const lastPage = ref(1);
const totalTrips = ref(0);
const perPage = ref(5);
const sortDirection = ref<'asc' | 'desc'>('asc');
const expandedTripIds = ref<Set<number>>(new Set());

const totalPassengersShown = computed(() =>
    groupedRows.value.reduce((sum, trip) => sum + trip.passengers.length, 0),
);

let searchDebounceHandle: ReturnType<typeof setTimeout> | undefined;

const fetchGroupedRows = async () => {
    loadingRows.value = true;
    try {
        const response = await axios.post(`${baseentityurl}/grouped`, {
            searchtext: searchText.value,
            page: currentPage.value,
            per_page: perPage.value,
            sort_direction: sortDirection.value,
        });

        groupedRows.value = response.data.data;
        currentPage.value = response.data.current_page;
        lastPage.value = response.data.last_page;
        totalTrips.value = response.data.total;
    } catch (error) {
        console.error(error);
        toast.error('Failed to load the trip passenger manifest.');
    } finally {
        loadingRows.value = false;
    }
};

// Kept for readability at call sites that previously refreshed the table
// after create/edit/delete actions.
const triggerTableRefresh = fetchGroupedRows;

watch(searchText, () => {
    clearTimeout(searchDebounceHandle);
    searchDebounceHandle = setTimeout(() => {
        currentPage.value = 1;
        fetchGroupedRows();
    }, 350);
});

watch(perPage, () => {
    currentPage.value = 1;
    fetchGroupedRows();
});

const toggleSortDirection = () => {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    fetchGroupedRows();
};

const goToPage = (page: number) => {
    if (page < 1 || page > lastPage.value) return;
    currentPage.value = page;
    fetchGroupedRows();
};

const isExpanded = (tripId: number) => expandedTripIds.value.has(tripId);

const toggleExpand = (tripId: number) => {
    const next = new Set(expandedTripIds.value);
    if (next.has(tripId)) {
        next.delete(tripId);
    } else {
        next.add(tripId);
    }
    expandedTripIds.value = next;
};

onMounted(fetchGroupedRows);

/* Validation Schema aligned with Controller requirements and Database boundaries (EDIT mode) */
const schema = z.object({
    trip_id: z.preprocess(
        (val) => (val === '' || val === undefined ? undefined : Number(val)), 
        z.number({ required_error: 'Associated Trip Ticket ID is required' }).int().positive()
    ),
    passenger_name: z
        .string({ required_error: 'Passenger Name is required' })
        .min(2, 'Name must be at least 2 characters long')
        .max(100, 'Name cannot exceed 100 characters'),
    office: z.string().max(100, 'Office description cannot exceed 100 characters').optional().nullable(),
    contact_no: z.string().max(20, 'Contact number cannot exceed 20 characters').optional().nullable(),
});

const fieldconfig: any = {
    passenger_name: {
        label: 'Full Passenger Name',
        inputProps: {
            type: 'text',
            placeholder: 'Enter passenger full name',
        },
    },
    office: {
        label: 'Department / Agency Office',
        inputProps: {
            type: 'text',
            placeholder: 'e.g., GSO Office (Optional)',
        },
    },
    contact_no: {
        label: 'Contact Number',
        inputProps: {
            type: 'text',
            placeholder: 'e.g., 0917XXXXXXX (Optional)',
        },
    },
};

const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        trip_id: '' as any,
        passenger_name: '',
        office: '',
        contact_no: '',
    },
});

const resetForm = () => {
    form.resetForm();
    itemID.value = null;
    originalTripId.value = null;
};

// --- Bulk passenger create state (CREATE mode only). Kept separate from the
// single-record `form` above, which stays dedicated to editing one existing
// passenger, to avoid mixing a variable-length array into a vee-validate
// form built around fixed fields. ---
const bulkTripId = ref<number | undefined>(undefined);
const passengerRows = ref<{ passenger_name: string; office: string; contact_no: string }[]>([
    { passenger_name: '', office: '', contact_no: '' },
]);
const bulkErrors = ref<Record<string, string>>({});
const submittingBulk = ref(false);

const selectedBulkTripOption = computed(() =>
    tripOptions.value.find((t) => t.id === bulkTripId.value) ?? null,
);

// null capacity = no vehicle assigned to trip yet, treated as unlimited
const seatsRemainingForNewRows = computed(() => {
    const trip = selectedBulkTripOption.value;
    if (!trip || trip.capacity === null) return null;
    return trip.remaining - passengerRows.value.length;
});

const canAddPassengerRow = computed(() => {
    if (!bulkTripId.value) return false;
    return seatsRemainingForNewRows.value === null || seatsRemainingForNewRows.value > 0;
});

// Switching trips mid-form resets the batch — avoids stale rows exceeding a
// smaller trip's capacity.
watch(bulkTripId, () => {
    passengerRows.value = [{ passenger_name: '', office: '', contact_no: '' }];
    bulkErrors.value = {};
});

const addPassengerRow = () => {
    if (!canAddPassengerRow.value) return;
    passengerRows.value.push({ passenger_name: '', office: '', contact_no: '' });
};

const removePassengerRow = (index: number) => {
    if (passengerRows.value.length <= 1) return;
    passengerRows.value.splice(index, 1);
};

const resetBulkForm = () => {
    bulkTripId.value = undefined;
    passengerRows.value = [{ passenger_name: '', office: '', contact_no: '' }];
    bulkErrors.value = {};
};

const bulkPassengerEntrySchema = z.object({
    passenger_name: z
        .string({ required_error: 'Passenger Name is required' })
        .min(2, 'Name must be at least 2 characters long')
        .max(100, 'Name cannot exceed 100 characters'),
    office: z.string().max(100, 'Office description cannot exceed 100 characters').optional().or(z.literal('')),
    contact_no: z.string().max(20, 'Contact number cannot exceed 20 characters').optional().or(z.literal('')),
});

const bulkFormSchema = z.object({
    trip_id: z.number({ required_error: 'Please select a trip' }).int().positive(),
    passengers: z.array(bulkPassengerEntrySchema).min(1, 'Add at least one passenger'),
});

const onSubmitBulk = async () => {
    bulkErrors.value = {};

    const parsed = bulkFormSchema.safeParse({
        trip_id: bulkTripId.value,
        passengers: passengerRows.value,
    });

    if (!parsed.success) {
        for (const issue of parsed.error.issues) {
            bulkErrors.value[issue.path.join('.')] = issue.message;
        }
        toast.error(parsed.error.issues[0]?.message ?? 'Please fix the highlighted fields.');
        return;
    }

    submittingBulk.value = true;
    try {
        const response = await axios.post(`${baseentityurl}/bulk`, {
            trip_id: parsed.data.trip_id,
            passengers: parsed.data.passengers,
        });

        toast.success(response.data?.message ?? `${passengerRows.value.length} passenger(s) assigned to trip successfully.`);

        resetBulkForm();
        await triggerTableRefresh();
        showDialogForm.value = false;
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors || {};
            bulkErrors.value = Object.fromEntries(
                Object.entries(errors).map(([key, msgs]) => [key, (msgs as string[])[0]]),
            );
            const firstError = Object.values(errors)[0] as string[] | undefined;
            toast.error(firstError?.[0] ?? 'Validation failed. Please verify manifest input data parameters.');
        } else {
            console.error(error);
            toast.error('An unexpected service lifecycle exception occurred.');
        }
    } finally {
        submittingBulk.value = false;
    }
};

const handleOpenDialogForm = () => {
    resetForm();
    resetBulkForm();
    mode.value = 'create';
    showDialogForm.value = true;
    fetchPendingTrips();
};

const onSubmit = async (values: any) => {
    try {
        // Notice: Controller validator checks for 'trip_ticket_record_id'. We adjust payload keys gracefully.
        const payload = {
            ...values,
            trip_ticket_record_id: values.trip_id
        };

        if (mode.value === 'create') {
            await axios.post(baseentityurl, payload);
            toast.success(`${baseentityname} assigned to manifest successfully.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, payload);
            toast.success(`${baseentityname} manifest record updated.`);
        }

        resetForm();
        await triggerTableRefresh();
        showDialogForm.value = false;
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            const firstError = Object.values(error.response.data.errors || {})[0] as string[] | undefined;
            toast.error(firstError?.[0] ?? 'Validation failed. Please verify manifest input data parameters.');
        } else {
            console.error(error);
            toast.error('An unexpected service lifecycle exception occurred.');
        }
    }
};

const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await fetchPendingTrips();

        const response = await axios.get(`${baseentityurl}/${id}`);
        
        // Map backend state back safely to visual fields form
        const data = response.data;

        originalTripId.value = data.trip_id;

        await ensureTripInOptions(data.trip_id);

        form.setValues({
            trip_id: data.trip_id,
            passenger_name: data.passenger_name,
            office: data.office || '',
            contact_no: data.contact_no || '',
        });
        
        showDialogForm.value = true;
    } catch (error) {
        toast.error(`Failed to lookup requested passenger record identifier data.`);
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
        toast.success(`${baseentityname} dropped completely from manifest registry.`);
        
        await triggerTableRefresh();
        showDeleteDialog.value = false;
    } catch (error) {
        toast.error('An exception dropped execution during removal workflow.');
    }
};
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            
            <div class="flex items-center gap-2 py-2">
                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="permissions.create" class="bg-blue-600 hover:bg-blue-700 text-white" @click="handleOpenDialogForm">
                        <Plus class="h-4 w-4 mr-1" /> Add Passenger to Trip
                    </Button>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Input
                    v-model="searchText"
                    placeholder="Search…"
                    class="max-w-sm"
                />
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40">
                            <th class="w-10 px-3 py-3"></th>
                            <th class="px-3 py-3 text-left font-medium">
                                <Button variant="ghost" class="h-auto p-0 font-medium" @click="toggleSortDirection">
                                    Trip No.
                                    <ArrowUpDown class="ml-2 h-4 w-4" />
                                </Button>
                            </th>
                            <th class="px-3 py-3 text-left font-medium">Passengers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loadingRows">
                            <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">Loading…</td>
                        </tr>
                        <tr v-else-if="groupedRows.length === 0">
                            <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">No results found.</td>
                        </tr>
                        <template v-for="trip in groupedRows" :key="trip.trip_id">
                            <tr
                                class="cursor-pointer border-b transition-colors hover:bg-muted/50"
                                @click="toggleExpand(trip.trip_id)"
                            >
                                <td class="px-3 py-3">
                                    <ChevronRight
                                        class="h-4 w-4 shrink-0 transition-transform"
                                        :class="{ 'rotate-90': isExpanded(trip.trip_id) }"
                                    />
                                </td>
                                <td class="px-3 py-3 font-mono text-xs whitespace-nowrap align-top">{{ trip.trip_no }}</td>
                                <td class="px-3 py-3 break-words whitespace-normal">
                                    {{ trip.passengers.map((p) => p.passenger_name).join(', ') }}
                                </td>
                            </tr>
                            <tr v-if="isExpanded(trip.trip_id)" class="border-b bg-muted/20">
                                <td colspan="3" class="p-0">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs text-muted-foreground">
                                                <th class="px-3 py-2 pl-10 text-left font-medium">Passenger Name</th>
                                                <th class="px-3 py-2 text-left font-medium">Office</th>
                                                <th class="px-3 py-2 text-left font-medium">Contact Number</th>
                                                <th class="w-10 px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="passenger in trip.passengers" :key="passenger.id" class="border-t">
                                                <td class="px-3 py-2 pl-10 font-medium">{{ passenger.passenger_name }}</td>
                                                <td class="px-3 py-2">{{ passenger.office || '—' }}</td>
                                                <td class="px-3 py-2">{{ passenger.contact_no || '—' }}</td>
                                                <td class="px-3 py-2">
                                                    <ReusableDropDownAction
                                                        :rowitem="passenger"
                                                        :on-edit="permissions.edit ? handleEdit : undefined"
                                                        :on-delete="permissions.delete ? openDeleteDialog : undefined"
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ groupedRows.length }} trip(s) shown, {{ totalPassengersShown }} passenger(s). Total {{ totalTrips }} trip(s).
                </p>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <p class="text-sm text-muted-foreground">Rows per page</p>
                        <Select :model-value="String(perPage)" @update:model-value="(val) => (perPage = Number(val))">
                            <SelectTrigger class="h-8 w-[70px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="size in [5, 10, 20, 50]" :key="size" :value="String(size)">
                                    {{ size }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <p class="text-sm text-muted-foreground">Page {{ currentPage }} of {{ lastPage }}</p>
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage <= 1" @click="goToPage(1)">
                            <ChevronsLeft class="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage >= lastPage" @click="goToPage(currentPage + 1)">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage >= lastPage" @click="goToPage(lastPage)">
                            <ChevronsRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[500px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Add' : 'Modify' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        {{ mode === 'create'
                            ? 'Select a pending trip, then add one or more passengers to the manifest at once.'
                            : "Input exact passenger specifications details to map out manifest scheduling." }}
                    </DialogDescription>

                    <!-- CREATE MODE: bulk passenger entry -->
                    <form v-if="mode === 'create'" class="space-y-4 pt-2" @submit.prevent="onSubmitBulk">
                        <div class="space-y-2">
                            <Label>Trip No. (Pending)</Label>
                            <Select
                                :model-value="bulkTripId ? String(bulkTripId) : undefined"
                                @update:model-value="(val) => (bulkTripId = val ? Number(val) : undefined)"
                            >
                                <SelectTrigger>
                                    <SelectValue :placeholder="loadingPendingTrips ? 'Loading pending trips…' : 'Select a pending trip'" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="trip in tripOptions"
                                        :key="trip.id"
                                        :value="String(trip.id)"
                                    >
                                        {{ trip.trip_no }}
                                        <span class="text-muted-foreground">
                                            — {{ trip.capacity !== null ? `${Math.max(trip.remaining ?? 0, 0)}/${trip.capacity} seats available` : 'no vehicle assigned' }}
                                        </span>
                                    </SelectItem>
                                    <div v-if="!loadingPendingTrips && tripOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                        No pending trips with open seats found.
                                    </div>
                                </SelectContent>
                            </Select>
                            <p v-if="bulkErrors['trip_id']" class="text-sm font-medium text-destructive">{{ bulkErrors['trip_id'] }}</p>
                        </div>

                        <div v-if="bulkTripId" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                            <span class="text-muted-foreground">Seats remaining for this batch</span>
                            <span class="font-medium">
                                {{ selectedBulkTripOption?.capacity !== null
                                    ? `${Math.max(seatsRemainingForNewRows ?? 0, 0)}/${selectedBulkTripOption?.capacity}`
                                    : 'Unlimited (no vehicle assigned)' }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(row, index) in passengerRows"
                                :key="index"
                                class="relative rounded-md border p-3 space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground">Passenger {{ index + 1 }}</span>
                                    <Button
                                        v-if="passengerRows.length > 1"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="h-6 w-6 text-destructive hover:text-destructive"
                                        @click="removePassengerRow(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>

                                <div class="space-y-2">
                                    <Label>Full Passenger Name</Label>
                                    <Input v-model="row.passenger_name" placeholder="Enter passenger full name" />
                                    <p v-if="bulkErrors[`passengers.${index}.passenger_name`]" class="text-sm font-medium text-destructive">
                                        {{ bulkErrors[`passengers.${index}.passenger_name`] }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label>Department / Agency Office</Label>
                                    <Input v-model="row.office" placeholder="e.g., GSO Office (Optional)" />
                                </div>

                                <div class="space-y-2">
                                    <Label>Contact Number</Label>
                                    <Input v-model="row.contact_no" placeholder="e.g., 0917XXXXXXX (Optional)" />
                                </div>
                            </div>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            :disabled="!canAddPassengerRow"
                            @click="addPassengerRow"
                        >
                            <Plus class="h-4 w-4 mr-1" />
                            {{ !bulkTripId
                                ? 'Select a trip first'
                                : canAddPassengerRow
                                    ? 'Add Another Passenger'
                                    : 'Vehicle seats fully occupied' }}
                        </Button>

                        <DialogFooter class="pt-4 gap-2">
                            <Button type="button" variant="outline" @click="showDialogForm = false">Cancel</Button>
                            <Button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="submittingBulk">
                                {{ submittingBulk ? 'Saving…' : `Assign ${passengerRows.length} Passenger${passengerRows.length > 1 ? 's' : ''}` }}
                            </Button>
                        </DialogFooter>
                    </form>

                    <!-- EDIT MODE: original single-passenger form, unchanged -->
                    <AutoForm
                        v-else
                        class="space-y-4 pt-2"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
                        <template #trip_id>
                            <FormField v-slot="{ componentField }" name="trip_id">
                                <FormItem>
                                    <FormLabel>Trip No. (Pending)</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingPendingTrips ? 'Loading pending trips…' : 'Select a pending trip'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="trip in tripOptions"
                                                :key="trip.id"
                                                :value="String(trip.id)"
                                            >
                                                {{ trip.trip_no }}
                                                <span class="text-muted-foreground">
                                                    — {{ trip.capacity !== null ? `${Math.max(trip.remaining ?? 0, 0)}/${trip.capacity} seats available` : 'no vehicle assigned' }}
                                                </span>
                                            </SelectItem>
                                            <div v-if="!loadingPendingTrips && tripOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No pending trips with open seats found.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <DialogFooter class="pt-4 gap-2">
                            <Button type="button" variant="outline" @click="showDialogForm = false">Cancel</Button>
                            <Button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white">
                                Save Record
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Remove Passenger From Manifest?`"
                :description="`Are you certain you wish to purge this passenger record file reference allocation? This can't be undone.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>