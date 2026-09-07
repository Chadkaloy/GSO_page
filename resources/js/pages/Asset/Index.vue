<script setup lang="ts">
/* Import Components */
import ReusableAlertDialog from '@/components/entitycomponents/ReusableAlertDialog.vue';
import ReusableDropDownAction from '@/components/entitycomponents/ReusableDropDownAction.vue';
import ReusableDataTable from '@/components/entitycomponents/ReusableDataTable.vue';
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

/* Import Utilities */
import { parseDate } from "@internationalized/date";
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { ArrowUpDown, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

/* Import Table Utilities */
import type { ColumnDef } from '@tanstack/vue-table';

/* Import Types */
import { BreadcrumbItem } from '@/types';

/* Base Entity Configuration */
const baseentityurl = '/assets';
const baseentityname = 'Asset';

/* Breadcrumbs */
const breadcrumbs: BreadcrumbItem[] = [
  {
    title: baseentityname,
    href: baseentityurl,
  },
];

const props = defineProps({
  categories: { type: Object, required: true },
  locations: { type: Object, required: true },
  manufacturers: { type: Object, required: true },
  users: { type: Object, required: true },
});

/* Define Props */
export interface Asset {
  id: number;
  category: any;
  category_id: string;
  location: any;
  location_id: string;
  manufacturer: any;
  manufacturer_id: string;
  assigned_to: any;
  assigned_to_user_id: string;
  asset_tag: string;
  name: string;
  serial_number: string;
  model_name: string;
  purchase_date: string;
  purchase_price: any;
  status: string;
  notes: string;
}

const statusEnum = {
  Deployed: 'Deployed',
  InStorage: 'In Storage',
  Maintenance: 'Maintenance',
  Retired: 'Retired',
  Broken: 'Broken',
};

/* Define Table Columns */
const columns: ColumnDef<Asset>[] = [
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
    accessorKey: 'name',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
      ),
    cell: ({ row }) => h('div', { class: 'break-words whitespace-normal' }, row.getValue('name')),
  },
  { accessorKey: 'asset_tag', header: 'Asset Tag', cell: ({ row }) => h('div', {}, row.getValue('asset_tag')) },
  { accessorKey: 'category.name', header: 'Category', cell: ({ row }) => h('div', {}, row.original.category?.name || '') },
  { accessorKey: 'location.name', header: 'Location', cell: ({ row }) => h('div', {}, row.original.location?.name || '') },
  { accessorKey: 'manufacturer.name', header: 'Manufacturer', cell: ({ row }) => h('div', {}, row.original.manufacturer?.name || '') },
  { accessorKey: 'assigned_to.name', header: 'Assigned To User', cell: ({ row }) => h('div', {}, row.original.assigned_to?.name || '') },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.original.status;
      const statusColors = {
        [statusEnum.Deployed]: 'text-green-500',
        [statusEnum.InStorage]: 'text-blue-500',
        [statusEnum.Maintenance]: 'text-yellow-500',
        [statusEnum.Retired]: 'text-gray-500',
        [statusEnum.Broken]: 'text-red-500',
      };
      return h('div', { class: `break-words whitespace-normal ${statusColors[status] || 'text-black'}` }, status);
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const rowitem = row.original;
      return h('div', { class: 'relative' },
        h(ReusableDropDownAction, { rowitem, onEdit: handleEdit, onDelete: openDeleteDialog })
      );
    },
  },
];

/* Dialog State */
const showDialogForm = ref(false);
const mode = ref('create');
const itemID = ref<number | null>(null);

/* Form Schema and Config */
const schema = z.object({
  name: z.string().min(3).max(255),
  serial_number: z.string().max(255),
  model_name: z.string().max(255).optional(),
  category_id: z.enum(props.categories.map((item: any) => item.name)),
  location_id: z.enum(props.locations.map((item: any) => item.name)).nullable().optional(),
  manufacturer_id: z.enum(props.manufacturers.map((item: any) => item.name)).nullable().optional(),
  assigned_to_user_id: z.enum(props.users.map((item: any) => item.name)).nullable().optional(),
  asset_tag: z.string().max(255).optional(),
  purchase_date: z.coerce.date().optional(),
  purchase_price: z.coerce.number().nonnegative().optional(),
  status: z.enum(Object.values(statusEnum) as [string, ...string[]]),
  notes: z.string().max(1000).nullable().optional(),
});

const fieldconfig: any = {
  name: { label: 'Name', inputProps: { type: 'text', class: 'uppercase', placeholder: 'Enter asset name' } },
  serial_number: { label: 'Serial Number', inputProps: { type: 'text', class: 'uppercase', placeholder: 'Enter serial number' } },
  model_name: { label: 'Model Name', inputProps: { type: 'text', class: 'uppercase', placeholder: 'Enter model name' } },
  category_id: { label: 'Select Category', component: 'select', inputProps: { placeholder: 'Choose a category...' } },
  location_id: { label: 'Select Location', component: 'select', inputProps: { placeholder: 'Choose a location...' } },
  manufacturer_id: { label: 'Select Manufacturer', component: 'select', inputProps: { placeholder: 'Choose a manufacturer...' } },
  assigned_to_user_id: { label: 'Select Assigned User', component: 'select', inputProps: { placeholder: 'Choose a user...' } },
  asset_tag: { label: 'Asset Tag', inputProps: { type: 'text', class: 'uppercase', placeholder: 'Enter asset tag' } },
  purchase_date: { label: 'Purchase Date', inputProps: { type: 'date' } },
  purchase_price: { label: 'Purchase Price', inputProps: { type: 'number', step: '0.01', placeholder: 'Enter purchase price' } },
  status: { label: 'Status', component: 'select' },
  notes: { label: 'Notes', component: 'textarea', inputProps: { class: 'uppercase', placeholder: 'Enter additional notes' } },
};

const form = useForm({
  validationSchema: toTypedSchema(schema),
  initialValues: {
    category_id: null,
    location_id: null,
    manufacturer_id: null,
    assigned_to_user_id: null,
    asset_tag: '',
    name: '',
    serial_number: '',
    model_name: '',
    purchase_date: null,
    purchase_price: 0,
    status: null,
    notes: '',
  },
});

/* Reset Form */
const resetForm = () => {
  form.resetForm();
  itemID.value = null;
};

/* Open Create Form */
const handleOpenDialogForm = () => {
  showDialogForm.value = true;
  mode.value = 'create';
};

const tableRef = ref<InstanceType<typeof ReusableDataTable> | null>(null);

/* Submit Handler */
const onSubmit = async (values: any) => {
  try {
    const mappedValues = {
      ...values,
      category_id: props.categories.find((c: any) => c.name === values.category_id)?.id || null,
      location_id: props.locations.find((l: any) => l.name === values.location_id)?.id || null,
      manufacturer_id: props.manufacturers.find((m: any) => m.name === values.manufacturer_id)?.id || null,
      assigned_to_user_id: props.users.find((u: any) => u.name === values.assigned_to_user_id)?.id || null,
    };

    if (mode.value === 'create') {
      await axios.post(`${baseentityurl}`, mappedValues);
      toast.success(`${baseentityname} created successfully.`);
    } else {
      await axios.put(`${baseentityurl}/${itemID.value}`, mappedValues);
      toast.success(`${baseentityname} updated successfully.`);
    }
    resetForm();
    await tableRef.value?.fetchRows();
    showDialogForm.value = false;
  } catch (error: any) {
    if (error.response?.status === 422) {
      form.setErrors(error.response.data.errors);
      toast.error('Validation failed.');
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
    const data = response.data;
    const mappedData = {
      ...data,
      category_id: data.category?.name,
      location_id: data.location?.name,
      manufacturer_id: data.manufacturer?.name,
      assigned_to_user_id: data.assigned_to?.name,
      purchase_date: data.purchase_date ? parseDate(data.purchase_date.slice(0, 10)) : null,
    };
    form.setValues(mappedData);
    showDialogForm.value = true;
  } catch (error) {
    toast.error(`Failed to fetch ${baseentityname} data.`);
  }
};

/* Delete Logic */
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
    await tableRef.value?.fetchRows();
    showDeleteDialog.value = false;
  } catch {
    toast.error(`Failed to delete ${baseentityname}.`);
  }
};

/* ✅ Print Handler - Static clean table */
const handlePrint = () => {
  const tableEl = document.getElementById("print-section");
  if (tableEl) {
    const printContents = tableEl.outerHTML;
    const printWindow = window.open("", "", "height=800,width=1100");
    if (printWindow) {
      printWindow.document.write(`
        <html>
          <head>
            <title>Print ${baseentityname}</title>
            <style>
              body { font-family: sans-serif; padding: 20px; }
              table { width: 100%; border-collapse: collapse; }
              th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
              th { background-color: #f4f4f4; }
              button, .actions, .checkbox, [role="checkbox"] { display: none !important; }
            </style>
          </head>
          <body>
            <h2 style="margin-bottom: 10px;">${baseentityname} List</h2>
            ${printContents}
          </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
    }
  }
};
</script>

<template>
  <Head :title="baseentityname" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
      <!-- Action Buttons -->
      <div class="flex items-center gap-2 py-2">
        <div class="ml-auto flex items-center gap-2">
          <Button class="bg-green-600" @click="handlePrint">🖨 Print</Button>
          <Button class="bg-blue-600" @click="handleOpenDialogForm">
            <Plus class="h-4" /> Create {{ baseentityname }}
          </Button>
        </div>
      </div>

      <!-- Table Section (Printable) -->
      <div id="print-section">
        <ReusableDataTable
          ref="tableRef"
          :columns="columns"
          :baseentityname="baseentityname"
          :baseentityurl="baseentityurl"
        />
      </div>

      <!-- Dialog Form -->
      <Dialog v-model:open="showDialogForm">
        <DialogScrollContent class="sm:max-w-[800px]">
          <DialogHeader>
            <DialogTitle>{{ mode === 'create' ? 'Create' : 'Update' }} {{ baseentityname }}</DialogTitle>
          </DialogHeader>
          <DialogDescription>
            Use this form to edit the {{ baseentityname }} details.
          </DialogDescription>
          <AutoForm class="space-y-6" :form="form" :schema="schema" :field-config="fieldconfig" @submit="onSubmit">
            <DialogFooter>
              <Button type="submit" class="bg-yellow-600">
                {{ mode === 'create' ? 'Create' : 'Update' }}
              </Button>
            </DialogFooter>
          </AutoForm>
        </DialogScrollContent>
      </Dialog>

      <!-- Delete Confirmation -->
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
