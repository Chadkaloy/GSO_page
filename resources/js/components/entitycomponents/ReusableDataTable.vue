<script setup lang="ts">
/* Import necessary components and utilities */
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/components/ui/table/utils';
import { cn } from '@/lib/utils';
import {
    ColumnDef,
    ColumnFiltersState,
    ExpandedState,
    FlexRender,
    getCoreRowModel,
    getExpandedRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    SortingState,
    useVueTable,
    VisibilityState,
} from '@tanstack/vue-table';
import axios from 'axios';
import { ChevronDown, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

/* Define props passed to the component */
const props = defineProps({
    columns: Array as () => ColumnDef<any>[], // Defines the table columns
    baseentityurl: String, // Base URL for API requests
    baseentityname: String, // Name of the entity being displayed
});

/* Reactive variables for table data and state management */
const data = ref([]); // Holds the table data
const sorting = ref<SortingState>([]); // Sorting state
const globalFilter = ref(''); // Global search filter
const columnFilters = ref<ColumnFiltersState>([]); // Column-specific filters
const columnVisibility = ref<VisibilityState>({}); // Visibility state for columns
const rowSelection = ref({}); // Row selection state
const expanded = ref<ExpandedState>({}); // Expanded rows state
const pageSizesOptions = [5, 10, 20, 30]; // Options for rows per page
const loading = ref(true); // Loading state for the table

/* Pagination state and metadata */
const pagination = ref({
    paginationState: {
        pageIndex: 0, // Current page index
        pageSize: 5, // Number of rows per page
    },
    current_page: 0, // Current page number
    per_page: 5, // Rows per page
    last_page: 0, // Last page number
    total: 0, // Total number of rows
    path: '', // API path for pagination
    from: 0, // Starting row index
    to: 0, // Ending row index
    links: [] as any[], // Pagination links
});

const table = ref(); // Vue Table instance

// Tracks the in-flight /list request so a slower, older request (e.g. from
// an earlier keystroke during search) can never overwrite the table with
// stale results after a newer request has already resolved.
let activeRequestController: AbortController | null = null;

/**
 * Fetch rows from the API based on the current pagination, sorting, and filtering state.
 */
const fetchRows = async (resetPagination = false) => {
    // Cancel whatever request is still in flight — its response, if it ever
    // arrives, is now stale and must never be applied to the table.
    activeRequestController?.abort();
    const requestController = new AbortController();
    activeRequestController = requestController;

    loading.value = true;
    try {
        if (resetPagination) {
            pagination.value.paginationState.pageIndex = 0;
        }

        const response = await axios.post(
            `${props.baseentityurl}/list`,
            {
                page: pagination.value.paginationState.pageIndex + 1,
                per_page: pagination.value.paginationState.pageSize,
                sort_field: sorting.value[0]?.id,
                sort_direction: sorting.value.length == 0 ? undefined : sorting.value[0]?.desc ? 'desc' : 'asc',
                searchtext: globalFilter.value,
            },
            { signal: requestController.signal },
        );

        // If a newer request has since started, this response is stale —
        // drop it instead of updating the table.
        if (activeRequestController !== requestController) {
            return;
        }

        const pageData = response.data;
        data.value = pageData.data; // Update table data
        pagination.value = {
            ...pagination.value,
            current_page: pageData.current_page,
            per_page: pageData.per_page,
            last_page: pageData.last_page,
            total: pageData.total,
            path: pageData.path,
            from: pageData.from,
            to: pageData.to,
            links: pageData.links,
            paginationState: {
                pageIndex: pageData.current_page - 1,
                pageSize: pageData.per_page,
            },
        };
        console.log('Pagination:', pagination.value);
    } catch (error: any) {
        if (axios.isCancel(error) || error?.name === 'CanceledError') {
            // Expected: a newer request superseded this one. Not an error.
            return;
        }
        console.error('Error fetching data:', error);
    } finally {
        if (activeRequestController === requestController) {
            loading.value = false; // Only the latest request controls the loading indicator
        }
    }
};

/**
 * Initialize the Vue Table instance with the provided configuration.
 */
const initializeTable = () => {
    table.value = useVueTable({
        get data() {
            return data.value;
        },
        columns: (props.columns as ColumnDef<unknown, any>[]) ?? [], // Table columns
        getCoreRowModel: getCoreRowModel(), // Core row model
        getPaginationRowModel: getPaginationRowModel(), // Pagination row model
        getSortedRowModel: getSortedRowModel(), // Sorted row model
        getFilteredRowModel: getFilteredRowModel(), // Filtered row model
        getExpandedRowModel: getExpandedRowModel(), // Expanded row model
        get rowCount() {
            return pagination.value.total;
        },
        manualPagination: true, // Enable manual pagination
        manualSorting: true, // Enable manual sorting
        manualFiltering: true, // Enable manual filtering
        initialState: {
            pagination: {
                pageSize: 5, // Default rows per page
            },
        },
        // Event handlers for table state changes
        onColumnVisibilityChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnVisibility),
        onRowSelectionChange: (updaterOrValue) => valueUpdater(updaterOrValue, rowSelection),
        onExpandedChange: (updaterOrValue) => valueUpdater(updaterOrValue, expanded),
        state: {
            get globalFilter() {
                return globalFilter.value;
            },
            get sorting() {
                return sorting.value;
            },
            get columnFilters() {
                return columnFilters.value;
            },
            get columnVisibility() {
                return columnVisibility.value;
            },
            get rowSelection() {
                return rowSelection.value;
            },
            get expanded() {
                return expanded.value;
            },
            get pagination() {
                return pagination.value.paginationState;
            },
        },
        onPaginationChange: (updater) => {
            const newState = typeof updater === 'function' ? updater(pagination.value.paginationState) : updater;
            pagination.value.paginationState = newState;
            fetchRows(); // Fetch rows for the new page
        },
        onSortingChange: (updaterOrValue) => {
            valueUpdater(updaterOrValue, sorting); // Update sorting state
            fetchRows(); // Fetch sorted data
        },
        onGlobalFilterChange: (value) => {
            globalFilter.value = value; // Update the global filter state
            fetchRows(true); // Fetch filtered data
        },
    });
};

/**
 * Debounce function to limit the frequency of API calls for global filtering.
 * @param func - The function to debounce
 * @param delay - The delay in milliseconds
 */
function debounce(func: any, delay: any) {
    let timeout: any;
    return (...args: any) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), delay);
    };
}

/* Handle global filter changes with debounce */
const handleGlobalFilterChange = debounce((newValue: any) => {
    // Only send after 3 characters are typed
    if (newValue === '') {
        table.value.setGlobalFilter('');
        return;
    }
    table.value.setGlobalFilter(newValue);
}, 200); // 300ms debounce delay

/* Watch for changes in the global filter and update the table accordingly */
watch(globalFilter, (newValue) => {
    handleGlobalFilterChange(newValue);
});

/* Lifecycle hook to initialize the table and fetch data when the component is mounted */
onMounted(async () => {
    // loading.value = true; // Set loading to true
    await fetchRows(); // Fetch rows
    initializeTable(); // Initialize the table
    // loading.value = false; // Set loading to false after initialization
});

/* Expose the fetchRows function for external use */
defineExpose({
    fetchRows,
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <!-- Search input and column visibility dropdown -->
        <div class="flex items-center gap-2 py-4">
            <Input class="max-w-sm" v-model="globalFilter" placeholder="Search..." />
            <div class="ml-auto flex items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" class="ml-auto"> Columns <ChevronDown class="ml-2 h-4 w-4" /> </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuCheckboxItem
                            v-for="column in table.getAllColumns().filter((column: any) => column.getCanHide())"
                            :key="column.id"
                            class="capitalize"
                            :model-value="column.getIsVisible()"
                            @update:model-value="(value: any) => column.toggleVisibility(!!value)"
                        >
                            {{ column.id }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
        <div v-if="loading" class="flex items-center justify-center">
            Queries are fetching in the background...
        </div>
        <!-- Table component -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table?.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :class="
                                cn(
                                    { 'sticky bg-background/95': header.column.getIsPinned() },
                                    header.column.getIsPinned() === 'left' ? 'left-0' : 'right-0',
                                )
                            "
                        >
                            <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <template v-if="table?.getRowModel().rows?.length">
                        <template v-for="row in table?.getRowModel().rows" :key="row.id">
                            <TableRow>
                                <TableCell
                                    v-for="cell in row.getVisibleCells()"
                                    :key="cell.id"
                                    :class="
                                        cn(
                                            { 'sticky bg-background/95': cell.column.getIsPinned() },
                                            cell.column.getIsPinned() === 'left' ? 'left-0' : 'right-0',
                                        )
                                    "
                                >
                                    <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                </TableCell>
                            </TableRow>
                        </template>
                    </template>
                    <TableRow v-else>
                        <TableCell :colspan="props.columns?.length" class="h-24 text-center"> No results. </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination controls -->
        <div class="flex items-center justify-end space-x-2 py-4">
            <div class="flex-1 text-sm text-muted-foreground">
                {{ table?.getFilteredSelectedRowModel().rows.length }} of {{ table?.getFilteredRowModel().rows.length }} row(s) selected. Total
                {{ pagination.total }} entities
            </div>
            <div class="flex items-center space-x-2">
                <p class="text-sm font-medium">Rows per page</p>
                <Select :model-value="pagination.current_page.toString()" @update:model-value="(value) => table.setPageSize(Number(value))">
                    <SelectTrigger class="h-8 w-[70px]">
                        <SelectValue :placeholder="pagination.per_page.toString()" />
                    </SelectTrigger>
                    <SelectContent side="top">
                        <SelectItem v-for="size in pageSizesOptions" :key="size" :value="size.toString()">
                            {{ size }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center space-x-2">
                <!-- Previous Page Button -->
                <Button variant="outline" size="sm" :disabled="pagination.current_page === 1" @click="table.setPageIndex(0)">
                    <ChevronsLeft />
                </Button>
                <!-- Page Links -->
                <div class="flex items-center space-x-4">
                    <!-- Previous Page Button -->
                    <Button variant="outline" size="sm" :disabled="pagination.current_page === 1" @click="table.previousPage()">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <!-- Current Page Information -->
                    <span class="text-sm">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>

                    <!-- Next Page Button -->
                    <Button variant="outline" size="sm" :disabled="pagination.current_page === pagination.last_page" @click="table.nextPage()">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Next Page Button -->
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="pagination.current_page === pagination.last_page"
                    @click="table.setPageIndex(pagination.last_page - 1)"
                >
                    <ChevronsRight />
                </Button>
            </div>
        </div>
    </div>
    <!-- </div> -->
</template>