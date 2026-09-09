<script setup lang="ts">
/**
 * @file TripTicket Index Component (`Index.vue`)
 * @description Vue 3 Single File Component (SFC) for managing Trip Ticket operational logs,
 *              featuring a TanStack-powered data table, dynamic modal forms via Zod and Vee-Validate,
 *              Axios CRUD backend communication, and Vue Sonner notification toasts.
 */

/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import LocationCombobox from '@/components/entitycomponents/LocationCombobox.vue';
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
import { ArrowUpDown, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table';
import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/TripTicket';
const baseentityname = 'Trip Ticket';

/**
 * Breadcrumbs definition array for page navigation header layout.
 */
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

/**
 * TypeScript interface explicitly outlining columns and types for Trip Ticket records.
 */
export interface TripTicketRecord {
    id: number;
    trip_no: string;
    date_requested: string;
    requester_name: string;
    requester_office: string;
    destination: string;
    purpose: string;
    estimated_liters?: number | string | null;
    time_departure: string;
    time_return?: string | null;
    vehicle_id: number;
    // Eager-loaded from the vehicle relation (id + plate_no + capacity only).
    vehicle?: { id: number; plate_no: string; capacity: number } | null;
    // Count of TripTicketPassenger rows currently assigned to this trip.
    passengers_count?: number;
    driver_id: number;
    passenger_count: number;
    status: string;
    remarks?: string | null;
}

/**
 * Action permission flags shared globally via Inertia (see
 * AppServiceProvider::boot()) — derived from the logged-in user's accLevel.
 * Read once here so both the "Create" button and the row-actions dropdown
 * (via the columns definition below) use the same source.
 */
const permissions = computed(() => usePage().props.permissions as {
    create: boolean;
    edit: boolean;
    delete: boolean;
    view: boolean;
    print: boolean;
});

/**
 * Definition of TanStack table columns containing row selectors, sortable specs, and custom status badge renderers.
 */
const columns: ColumnDef<TripTicketRecord>[] = [
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
        accessorKey: 'id',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Trip ID', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'font-mono font-semibold text-center' }, row.getValue('id')),
    },
    {
        accessorKey: 'trip_no',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Trip No', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('trip_no')),
    },
    {
        accessorKey: 'date_requested',
        header: 'Date Requested',
        cell: ({ row }) => h('div', {}, row.getValue('date_requested')),
    },
    {
        accessorKey: 'requester_name',
        header: 'Requester Name',
        cell: ({ row }) => h('div', { class: 'break-words' }, row.getValue('requester_name')),
    },
    {
        accessorKey: 'destination',
        header: 'Destination',
        cell: ({ row }) => h('div', { class: 'break-words' }, row.getValue('destination')),
    },
    {
        id: 'available_seats',
        // accessorFn since this is derived from nested/related data, not a
        // flat column on the row (vehicle.capacity minus passengers_count).
        accessorFn: (row) => {
            if (!row.vehicle) return null;
            return row.vehicle.capacity - (row.passengers_count ?? 0);
        },
        header: 'Available Seats',
        cell: ({ row }) => {
            const vehicle = row.original.vehicle;
            if (!vehicle) {
                return h('div', { class: 'text-muted-foreground text-xs' }, 'No vehicle assigned');
            }
            const booked = row.original.passengers_count ?? 0;
            const remaining = vehicle.capacity - booked;
            return h(
                'div',
                { class: remaining <= 0 ? 'text-red-500 font-medium' : 'font-medium' },
                `${Math.max(remaining, 0)}/${vehicle.capacity} seats`,
            );
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            let badgeClass = 'px-2 py-1 rounded text-xs font-semibold ';
            if (status === 'Pending') badgeClass += 'bg-yellow-500/20 text-yellow-500';
            else if (status === 'Approved') badgeClass += 'bg-green-500/20 text-green-500';
            else if (status === 'Cancelled') badgeClass += 'bg-red-500/20 text-red-500';
            else badgeClass += 'bg-blue-500/20 text-blue-500';
            
            return h('span', { class: badgeClass }, status);
        },
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
                    onEdit: permissions.value.edit ? () => handleEdit(rowitem.id) : undefined,
                    onDelete: permissions.value.delete ? () => openDeleteDialog(rowitem.id) : undefined,
                    onPrint: () => handlePrintTripTicket(rowitem.id),
                }),
            );
        },
    },
];

/* Reactive State Declarations */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);

// Options for the "Department / Office" dropdown — pulled from Office
// Dictionary via the same /Office/list endpoint the Fuel Allocation page
// uses (there is no /Office/lookup route). Storing the office's NAME as the
// field's value (not an id) since requester_office is a plain string column
// on trip_ticket_record — this just makes sure what's stored here always
// exactly matches an Office Dictionary entry, which the fuel-allocation
// approval hook depends on to find the right office's balance.
const officeOptions = ref<string[]>([]);
const loadingOffices = ref(false);

const resolveOfficeLabel = (raw: any): string => {
    return raw.officeName ?? raw.office_name ?? raw.label ?? raw.name ?? raw.text ?? '';
};

const fetchOfficeOptions = async () => {
    loadingOffices.value = true;
    try {
        const response = await axios.post('/Office/list', { per_page: 1000 });
        const rawList: any[] = Array.isArray(response.data) ? response.data : (response.data?.data ?? []);
        officeOptions.value = rawList.map(resolveOfficeLabel).filter(Boolean);
    } catch (error) {
        toast.error('Failed to load office list.');
    } finally {
        loadingOffices.value = false;
    }
};

// Options for the "Vehicle" dropdown — only Available vehicles, showing plate_no.
const availableVehicles = ref<{ id: number; plate_no: string; brand?: string; model?: string; capacity?: number }[]>([]);
const loadingVehicles = ref(false);

const fetchAvailableVehicles = async () => {
    loadingVehicles.value = true;
    try {
        const response = await axios.get('/TripTicketVehicle/available');
        availableVehicles.value = response.data;
    } catch (error) {
        toast.error('Failed to load available vehicle list.');
    } finally {
        loadingVehicles.value = false;
    }
};

// Options for the "Driver" dropdown — only Active drivers, showing full_name.
const availableDrivers = ref<{ id: number; full_name: string }[]>([]);
const loadingDrivers = ref(false);

const fetchAvailableDrivers = async () => {
    loadingDrivers.value = true;
    try {
        const response = await axios.get('/TripTicketDriver/available');
        availableDrivers.value = response.data;
    } catch (error) {
        toast.error('Failed to load available driver list.');
    } finally {
        loadingDrivers.value = false;
    }
};

// When editing a trip whose vehicle/driver is no longer Available/Active
// (e.g. it's "In Use" precisely because it's assigned to this trip), that
// option won't be in the filtered dropdown list. Fetch it specifically and
// splice it in so the dropdown still shows a label instead of going blank.
const ensureVehicleInOptions = async (vehicleId: number) => {
    if (!vehicleId || availableVehicles.value.some((v) => v.id === vehicleId)) return;
    try {
        const response = await axios.get(`/TripTicketVehicle/${vehicleId}`);
        availableVehicles.value = [response.data, ...availableVehicles.value];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this vehicle.
    }
};

const ensureDriverInOptions = async (driverId: number) => {
    if (!driverId || availableDrivers.value.some((d) => d.id === driverId)) return;
    try {
        const response = await axios.get(`/TripTicketDriver/${driverId}`);
        availableDrivers.value = [response.data, ...availableDrivers.value];
    } catch (error) {
        // Non-fatal: dropdown just won't have a label for this driver.
    }
};

// How many passengers this trip is planned for, read live from the form so
// the vehicle dropdown re-filters as the user types into Passenger Count.
const plannedPassengerCount = computed(() => Number(form.values.passenger_count) || 0);

// Vehicle options annotated with remaining room for this trip's planned
// passenger count, and filtered down to only vehicles that can fit them.
// The currently selected vehicle (if editing) is always kept in the list
// even if it no longer has enough room, so the dropdown never goes blank —
// its label will just show a shortfall instead of a comfortable remainder.
const vehicleOptions = computed(() => {
    const currentVehicleId = Number(form.values.vehicle_id) || null;

    return availableVehicles.value
        .map((vehicle) => ({
            ...vehicle,
            remaining: (vehicle.capacity ?? 0) - plannedPassengerCount.value,
        }))
        .filter((vehicle) => vehicle.remaining >= 0 || vehicle.id === currentVehicleId)
        .sort((a, b) => a.plate_no.localeCompare(b.plate_no));
});

// Table auto-refresh reactive key state cache-buster
const refreshKey = ref(0);
const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);

/**
 * Triggers re-rendering of the data table component AND explicitly
 * refetches — relying on the :key remount alone was inconsistent about
 * refreshing immediately.
 */
const triggerTableRefresh = async () => {
    refreshKey.value += 1;

    if (tableRef.value && typeof tableRef.value.fetchRows === 'function') {
        await tableRef.value.fetchRows();
    }
};

/**
 * Zod validation schema outlining strict constraints, types, and error rules for trip ticket fields.
 */
const schema = z.object({
    trip_no: z.string().min(1, 'Trip number is required').max(50),
    date_requested: z.string().min(1, 'Date requested is required'),
    requester_name: z.string().min(1, 'Requester name is required').max(100),
    requester_office: z.string().min(1, 'Office name is required').max(100),
    destination: z.string().min(1, 'Destination is required').max(200),
    purpose: z.string().min(1, 'Purpose description is required'),
    estimated_liters: z.preprocess(
        (val) => (val === '' || val === null || val === undefined ? undefined : Number(val)),
        z.number().min(0, 'Must be zero or more').optional(),
    ),
    time_departure: z.string().min(1, 'Departure timeframe configuration required'),
    time_return: z.string().optional().nullable(),
    passenger_count: z.preprocess((val) => (val === '' || val === null ? 0 : Number(val)), z.number().int().nonnegative().default(0)),
    vehicle_id: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Vehicle reference is required' }).int().positive()),
    driver_id: z.preprocess((val) => (val === '' || val === undefined ? undefined : Number(val)), z.number({ required_error: 'Driver assignment reference is required' }).int().positive()),
    remarks: z.string().optional().nullable(),
});

/**
 * Configuration mapping specifying layout labels and input element types for AutoForm UI generation.
 */
const fieldconfig: any = {
    trip_no: { label: 'Trip Ticket Number' },
    date_requested: { 
        label: 'Date Requested', 
        inputProps: { type: 'date' } 
    },
    requester_name: { label: 'Requester Full Name' },
    requester_office: { label: 'Department / Office' },
    destination: { label: 'Target Destination' },
    purpose: { 
        label: 'Purpose of Trip', 
        inputProps: { type: 'textarea' } 
    },
    estimated_liters: {
        label: 'Estimated Fuel (Liters, Optional)',
        inputProps: { type: 'number', min: 0, step: 0.01, placeholder: 'e.g., 6' },
        description: "Leave blank to skip fuel tracking for this trip. If set, this amount is reserved from the office's fuel allocation once the trip is Approved.",
    },
    time_departure: { 
        label: 'Departure Schedule', 
        inputProps: { type: 'datetime-local' } 
    },
    time_return: { 
        label: 'Expected Return (Optional)', 
        inputProps: { type: 'datetime-local' } 
    },
    passenger_count: { 
        label: 'Total Passenger Count', 
        inputProps: { type: 'number', min: 0 } 
    },
    remarks: { 
        label: 'Operational Remarks', 
        inputProps: { type: 'textarea' } 
    },
};

/**
 * Vee-Validate form utility initialization bound with validation schema and default values.
 */
const form = useForm({
    validationSchema: toTypedSchema(schema),
    initialValues: {
        trip_no: '',
        date_requested: new Date().toISOString().split('T')[0],
        requester_name: '',
        requester_office: '',
        destination: '',
        purpose: '',
        estimated_liters: '' as any,
        time_departure: '',
        time_return: '',
        vehicle_id: '' as any,
        driver_id: '' as any,
        passenger_count: 0,
        remarks: '',
    },
});

/**
 * Resets the form inputs and clears active tracker state variables.
 */
const resetForm = () => {
    form.resetForm();
    itemID.value = null;
};

/**
 * Opens the dialog form modal in 'create' mode with clean defaults.
 */
const handleOpenDialogForm = () => {
    resetForm();
    mode.value = 'create';
    showDialogForm.value = true;
    fetchAvailableVehicles();
    fetchAvailableDrivers();
    fetchOfficeOptions();
};

/**
 * Handles form submissions for creating new records or updating existing records via Axios endpoints.
 */
const onSubmit = async (values: any) => {
    try {
        if (mode.value === 'create') {
            await axios.post(baseentityurl, values);
            toast.success(`${baseentityname} created successfully.`);
        } else if (mode.value === 'edit') {
            await axios.put(`${baseentityurl}/${itemID.value}`, values);
            toast.success(`${baseentityname} updated successfully.`);
        }

        resetForm();
        await triggerTableRefresh();
        showDialogForm.value = false;
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.setErrors(error.response.data.errors);
            toast.error('Validation failed. Please verify fields syntax.');
        } else {
            console.error(error);
            toast.error('An unexpected routing endpoint issue occurred.');
        }
    }
};

/**
 * Fetches targeted record configuration values by ID, formats datetime strings for input compatibility, and opens the modal in 'edit' mode.
 */
const handleEdit = async (id: number) => {
    try {
        mode.value = 'edit';
        itemID.value = id;

        await Promise.all([fetchAvailableVehicles(), fetchAvailableDrivers(), fetchOfficeOptions()]);

        const response = await axios.get(`${baseentityurl}/${id}`);
        
        const data = { ...response.data };
        if (data.time_departure) data.time_departure = data.time_departure.replace(' ', 'T').slice(0, 16);
        if (data.time_return) data.time_return = data.time_return.replace(' ', 'T').slice(0, 16);

        await Promise.all([
            ensureVehicleInOptions(data.vehicle_id),
            ensureDriverInOptions(data.driver_id),
        ]);

        form.setValues(data);
        showDialogForm.value = true;
    } catch (error) {
        toast.error(`Failed to gather original data for update lookup.`);
    }
};

/* Delete Confirmation Dialog Reactive States */
const showDeleteDialog = ref(false);
const itemIDToDelete = ref<number | null>(null);

/**
 * Escapes text for safe interpolation into the print window's HTML.
 */
const escapeHtml = (value: unknown): string => {
    const str = value === null || value === undefined ? '' : String(value);
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
};

/**
 * Builds the printable "Vehicle Trip Ticket" markup for one trip, matching
 * the physical DOTC form: instructions block, requester-filled fields,
 * blank Charging/Liters (filled by hand at the pump), blank
 * Authorized-By/Approved-by signature lines, and a blank gasoline-station
 * section. Rendered twice side-by-side to mirror the two-copy paper sheet.
 */
const buildTripTicketCopyHtml = (trip: any, signatories: { municipal_administrator_name: string | null; municipal_mayor_name: string | null }): string => {
    const passengerNames = Array.isArray(trip.passengers)
        ? trip.passengers.map((p: any) => p.passenger_name).filter(Boolean).join(', ')
        : '';

    const adminName = signatories.municipal_administrator_name ?? '';
    const mayorName = signatories.municipal_mayor_name ?? '';
    const liters = trip.estimated_liters !== null && trip.estimated_liters !== undefined && trip.estimated_liters !== ''
        ? `${trip.estimated_liters} L`
        : '';

    return `
        <div class="copy">
            <div class="letterhead" style="display: flex; align-items: center; justify-content: center; gap: 10px; text-align: center;">
                <img src="/Municipal Logo.png" alt="Municipal Logo" style="width: 48px; height: 48px; object-fit: contain;">
                <div>
                    <div style="font-size: 9.5pt; font-family: serif; line-height: 1.1;">Republic of the Philippines</div>
                    <div style="font-size: 9.5pt; font-family: serif; line-height: 1.1;">Province of Bukidnon</div>
                    <div class="municipality" style="font-weight: bold; font-size: 11pt; font-family: serif; line-height: 1.2;">Municipality of Maramag</div>
                </div>
            </div>
            <div class="letterhead" style="text-align: center; margin-top: 2px;">
                <div class="dept" style="font-size: 9pt;">DEPARTMENT OF TRANSPORTATION &amp; COMMUNICATION</div>
                <div class="formtitle" style="font-weight: bold; font-size: 10pt; margin-top: 1px;">VEHICLE TRIP TICKET</div>
            </div>

            <div class="instructions" style="font-size: 9px; margin-bottom: 2mm;">
                <div>INSTRUCTIONS:</div>
                <ol style="margin: 1px 0 0 14px; padding: 0;">
                    <li style="margin-bottom: 0.5px;">Make 3 copies of trip tickets.</li>
                    <li style="margin-bottom: 0.5px;">2nd copy will be returned to the GSO services. Further travel is prohibited in case driver fails to comply this instruction.</li>
                    <li style="margin-bottom: 0.5px;">All trip tickets must be signed by the person requesting the vehicle</li>
                    <li style="margin-bottom: 0.5px;">Indicate where to CHARGE &amp; put number of liters to be used.</li>
                    <li style="margin-bottom: 0.5px;">All trip tickets must be initialed by the GSO before the approval of the LCE for record purposes.</li>
                </ol>
            </div>

            <div class="section-title" style="font-size: 9px; margin: 2mm 0 1mm;">TO BE FILLED UP BY PERSONNEL REQUESTING THE USE OF DEPARTMENT VEHICLE:</div>

            <table class="field-grid">
                <tr>
                    <td class="label">DATE:</td>
                    <td class="value">${escapeHtml(trip.date_requested)}</td>
                    <td class="label">VEHICLE PLATE NO.:</td>
                    <td class="value">${escapeHtml(trip.vehicle?.plate_no)}</td>
                </tr>
                <tr>
                    <td class="label">NAME OF DRIVER:</td>
                    <td class="value">${escapeHtml(trip.driver?.full_name)}</td>
                    <td class="label">OFFICE:</td>
                    <td class="value">${escapeHtml(trip.requester_office)}</td>
                </tr>
            </table>

            <div class="stacked-field">
                <div class="label">AUTHORIZED PASSENGER:</div>
                <div class="value full-line">${escapeHtml(passengerNames)}</div>
            </div>

            <div class="stacked-field">
                <div class="label">DESTINATION:</div>
                <div class="value full-line">${escapeHtml(trip.destination)}</div>
            </div>

            <div class="stacked-field">
                <div class="label">PURPOSE:</div>
                <div class="value full-line">${escapeHtml(trip.purpose)}</div>
            </div>

            <table class="field-grid">
                <tr>
                    <td class="label">CHARGING:</td>
                    <td class="value blank">${escapeHtml(trip.requester_office)}</td>
                    <td class="label">NUMBER OF LITERS:</td>
                    <td class="value blank">${escapeHtml(liters)}</td>
                </tr>
            </table>

            <table class="field-grid">
                <tr class="bold-row">
                    <td class="label">REQUESTING OFFICER:</td>
                    <td class="value">${escapeHtml(trip.requester_name)}</td>
                    <td class="label">AUTHORIZED BY:</td>
                    <td class="value blank">${escapeHtml(adminName)}</td>
                </tr>
            </table>

            <div class="approval-block" style="margin-top: 3mm;">
                <div class="approval-caption" style="margin-bottom: 1mm;">Approved by:</div>
                <div class="sig-row">
                    <div class="sig-cell">
                        <div class="sig-name" style="text-align: center; font-weight: bold; font-size: 9.5px; min-height: 11px;">${escapeHtml(adminName)}</div>
                        <div class="sig-bar"></div>
                        <div class="sig-caption">Municipal Administrator</div>
                    </div>
                    <div class="sig-cell">
                        <div class="sig-name" style="text-align: center; font-weight: bold; font-size: 9.5px; min-height: 11px;">${escapeHtml(mayorName)}</div>
                        <div class="sig-bar"></div>
                        <div class="sig-caption">Municipal Mayor</div>
                    </div>
                </div>
                <div class="section-divider" style="margin-top: 2mm;"></div>
            </div>

            <div class="gas-section" style="margin-top: 2mm; padding-top: 1mm;">
                <div class="section-title" style="font-size: 9px; margin: 1mm 0;">TO BE FILLED-UP ONLY BY THE GASOLINE STATION CONCERN:</div>
                <table class="field-grid">
                    <tr>
                        <td class="label">DRIVER'S NAME:</td>
                        <td class="value blank">${escapeHtml(trip.driver?.full_name ?? '')}</td>
                        <td class="label">PLATE NO.:</td>
                        <td class="value blank">${escapeHtml(trip.vehicle?.plate_no ?? '')}</td>
                    </tr>
                    <tr>
                        <td class="label">DIESOLINE:</td>
                        <td class="value blank">&nbsp;liters</td>
                        <td class="label">OIL:</td>
                        <td class="value blank"></td>
                    </tr>
                    <tr>
                        <td class="label">GASOLINE:</td>
                        <td class="value blank">&nbsp;liters</td>
                        <td class="label">FLUID:</td>
                        <td class="value blank"></td>
                    </tr>
                    <tr>
                        <td class="label">GREASE OIL:</td>
                        <td class="value blank">&nbsp;liters</td>
                        <td class="label">OTHERS:</td>
                        <td class="value blank"></td>
                    </tr>
                </table>
                <div class="certify" style="margin: 1mm 0; font-size: 8.5px;">I HEREBY CERTIFY that the number of liters stated above is true &amp; correct:</div>
                <div class="sig-row">
                    <div class="sig-cell">
                        <div class="sig-space" style="height: 5mm;"></div>
                        <div class="sig-bar"></div>
                        <div class="sig-caption">Gasoline Station In-Charge</div>
                    </div>
                    <div class="sig-cell">
                        <div class="sig-space" style="height: 5mm;"></div>
                        <div class="sig-bar"></div>
                        <div class="sig-caption">Driver</div>
                    </div>
                </div>
            </div>
        </div>
    `;
};

/**
 * Fetches full trip data (including driver/vehicle/passenger names) and
 * opens a new print-ready window with two side-by-side copies of the
 * Vehicle Trip Ticket, matching the physical paper form layout.
 */
const handlePrintTripTicket = async (id: number) => {
    try {
        const [tripResponse, signatoryResponse] = await Promise.all([
            axios.get(`${baseentityurl}/${id}`),
            // Non-fatal if this fails — printing shouldn't be blocked by the
            // signatory settings endpoint; the signature lines just print
            // blank (same as before this feature existed) on failure.
            axios.get('/TripTicketSignatory/current').catch(() => ({
                data: { municipal_administrator_name: null, municipal_mayor_name: null },
            })),
        ]);

        const trip = tripResponse.data;
        const signatories = signatoryResponse.data;

        const copyHtmlLeft = buildTripTicketCopyHtml(trip, signatories);
        const copyHtmlRight = buildTripTicketCopyHtml(trip, signatories);

        const printWindow = window.open('', '_blank', 'width=1400,height=900');
        if (!printWindow) {
            toast.error('Pop-up blocked. Please allow pop-ups for this site to print.');
            return;
        }

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8" />
                <title>Vehicle Trip Ticket - ${escapeHtml(trip.trip_no)}</title>
                <style>
                    @page { size: legal landscape; margin: 5mm; }
                    * { box-sizing: border-box; }
                    html, body { width: 100%; height: 100%; }
                    body {
                        font-family: 'Times New Roman', Times, serif;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }
                    .sheet {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        column-gap: 6mm;
                        width: 100%;
                        align-items: start;
                    }
                    .copy {
                        border-right: 1px dashed #999;
                        padding: 2mm 5mm 2mm 2mm;
                        font-size: 10.5px;
                        min-width: 0;
                    }
                    .copy:last-child { border-right: none; }
                    .letterhead { text-align: center; margin-bottom: 2.5mm; }
                    .municipality { font-weight: bold; }
                    .dept { font-weight: bold; letter-spacing: 0.2px; }
                    .formtitle { font-weight: bold; }
                    .instructions { margin-bottom: 2.5mm; }
                    .instructions ol { margin: 2px 0 0 14px; padding: 0; }
                    .instructions li { margin-bottom: 1px; }
                    .section-title { font-weight: bold; margin: 3mm 0 1.5mm; font-size: 9.5px; }
                    table.field-grid { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
                    table.field-grid td { padding: 2px 3px; vertical-align: bottom; }
                    td.label { white-space: nowrap; font-weight: bold; width: 1%; font-size: 9.5px; }
                    td.value { border-bottom: 1px solid #000; min-width: 30px; font-size: 10.5px; }
                    td.value.blank { min-height: 14px; }
                    .stacked-field { margin-bottom: 2mm; }
                    .stacked-field .label { font-weight: bold; display: block; margin-bottom: 1px; font-size: 9.5px; }
                    .stacked-field .full-line { border-bottom: 1px solid #000; min-height: 14px; font-size: 10.5px; }
                    .approval-block { margin-top: 4mm; }
                    .approval-caption { margin-bottom: 1.5mm; font-size: 9.5px; }
                    .bold-row td { font-weight: bold; }
                    .sig-row {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        column-gap: 6mm;
                    }
                    .sig-cell { min-width: 0; }
                    .sig-space { height: 8mm; }
                    .sig-bar {
                        border-top: 1px solid #000;
                        border-bottom: 1px solid #000;
                        height: 1.5px;
                        margin-bottom: 1px;
                    }
                    .sig-caption {
                        text-align: center;
                        font-weight: bold;
                        font-size: 9.5px;
                        color: #000;
                    }
                    .section-divider { border-bottom: 1px solid #999; margin-top: 3mm; }
                    .gas-section { margin-top: 3mm; padding-top: 1.5mm; border-top: 1px solid #000; }
                    .certify { margin: 1.5mm 0; font-size: 9px; }
                    @media print {
                        html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    }
                </style>
            </head>
            <body>
                <div class="sheet">
                    ${copyHtmlLeft}
                    ${copyHtmlRight}
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();

        printWindow.onload = () => {
            printWindow.focus();
            printWindow.print();
        };
    } catch (error) {
        toast.error('Failed to load trip data for printing.');
    }
};

/**
 * Opens the deletion confirmation dialog for a targeted item ID.
 */
const openDeleteDialog = (id: number) => {
    itemIDToDelete.value = id;
    showDeleteDialog.value = true;
};

/**
 * Sends a DELETE request to backend services via Axios and triggers a table data refresh upon success.
 */
const handleDelete = async () => {
    try {
        if (!itemIDToDelete.value) return;
        await axios.delete(`${baseentityurl}/${itemIDToDelete.value}`);
        toast.success(`${baseentityname} completely removed from operations registry.`);
        
        await triggerTableRefresh();
        showDeleteDialog.value = false;
    } catch (error) {
        toast.error('Could not cleanly request record destruction.');
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
                        <Plus class="h-4 w-4 mr-1" /> Create {{ baseentityname }}
                    </Button>
                </div>
            </div>

            <!-- Reusable Data Table with reactive cache-buster refresh key binding -->
            <ReusableDataTable
                :key="refreshKey"
                ref="tableRef"
                :columns="columns"
                :baseentityname="baseentityname"
                :baseentityurl="baseentityurl"
            />

            <!-- Create / Update Dialog Form Modal Component -->
            <Dialog v-model:open="showDialogForm">
                <DialogContent class="sm:max-w-[550px] max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{{ mode === 'create' ? 'Create' : 'Modify' }} {{ baseentityname }}</DialogTitle>
                    </DialogHeader>
                    <DialogDescription>
                        Complete all input parameters carefully to ensure seamless fleet log lifecycle deployment operations.
                    </DialogDescription>
                    
                    <AutoForm
                        class="space-y-4 pt-2"
                        :form="form"
                        :schema="schema"
                        :field-config="fieldconfig"
                        @submit="onSubmit"
                    >
                        <template #requester_office>
                            <FormField v-slot="{ componentField }" name="requester_office">
                                <FormItem>
                                    <FormLabel>Department / Office</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue || undefined"
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingOffices ? 'Loading offices…' : 'Select an office'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="officeName in officeOptions"
                                                :key="officeName"
                                                :value="officeName"
                                            >
                                                {{ officeName }}
                                            </SelectItem>
                                            <div v-if="!loadingOffices && officeOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No offices found in Office Dictionary.
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <template #vehicle_id>
                            <FormField v-slot="{ componentField }" name="vehicle_id">
                                <FormItem>
                                    <FormLabel>Vehicle (Plate No.)</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingVehicles ? 'Loading vehicles…' : 'Select a vehicle'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="vehicle in vehicleOptions"
                                                :key="vehicle.id"
                                                :value="String(vehicle.id)"
                                            >
                                                {{ vehicle.plate_no }} — {{ Math.max(vehicle.remaining, 0) }}/{{ vehicle.capacity }} seats available
                                                <span v-if="vehicle.remaining < 0" class="text-red-500">(over capacity)</span>
                                            </SelectItem>
                                            <div v-if="!loadingVehicles && vehicleOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No vehicles with enough room for {{ plannedPassengerCount }} passenger(s).
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <!-- Uses the location-autocomplete input so previously-typed
                             destinations (cached via LocationController::remember()
                             on the backend) show up as suggestions. Still a free-text
                             field — the person can always type a brand new destination. -->
                        <template #destination>
                            <FormField v-slot="{ componentField }" name="destination">
                                <FormItem>
                                    <FormLabel>Target Destination</FormLabel>
                                    <LocationCombobox
                                        :model-value="componentField.modelValue ?? ''"
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                        placeholder="Type a destination…"
                                    />
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </template>

                        <template #driver_id>
                            <FormField v-slot="{ componentField }" name="driver_id">
                                <FormItem>
                                    <FormLabel>Driver</FormLabel>
                                    <Select
                                        :model-value="componentField.modelValue ? String(componentField.modelValue) : undefined"
                                        @update:model-value="(val) => componentField['onUpdate:modelValue']?.(val ? Number(val) : undefined)"
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue :placeholder="loadingDrivers ? 'Loading drivers…' : 'Select a driver'" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="driver in availableDrivers"
                                                :key="driver.id"
                                                :value="String(driver.id)"
                                            >
                                                {{ driver.full_name }}
                                            </SelectItem>
                                            <div v-if="!loadingDrivers && availableDrivers.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                                                No active drivers found.
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
                                {{ mode === 'create' ? 'Save Ticket' : 'Update Changes' }}
                            </Button>
                        </DialogFooter>
                    </AutoForm>
                </DialogContent>
            </Dialog>

            <!-- Permanent Deletion Confirmation Alert Dialog Component -->
            <ReusableAlertDialog
                :open="showDeleteDialog"
                :title="`Purge ${baseentityname} Permanently?`"
                :description="`Are you certain you wish to eliminate record file ID ref: (${itemIDToDelete})? This cannot be reversed.`"
                @confirm="handleDelete"
                @cancel="showDeleteDialog = false"
            />
        </div>
    </AppLayout>
</template>