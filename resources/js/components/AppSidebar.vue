<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types'; // 👈 Reverted back to just NavItem
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, ListCheck, LocateOffIcon, Award, BookMarked, Archive, IdCard, ReceiptText, Armchair, Building2, Ticket} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        roles: ['super_admin','inventory_manager','inventory_user']
    },
    {
        title: 'Prs Purpose Dictionary',
        href: '/PrsPurposeDictionary',
        icon: BookMarked ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Emp Accounts Record',
        href: '/Employee',
        icon: Archive ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Emp Pgc Record',
        href: '/Pgc',
        icon: Archive ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Office Dictionary',
        href: '/Office',
        icon: BookMarked,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Inventory Dictionary',
        href: '/Inventory',
        icon: BookMarked,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Bincard Record',
        href: '/Bincard',
        icon: Archive,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Bincard Issued Record',
        href: '/BincardIssued',
        icon: Archive,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Emp Accountability Card',
        href: '/Accountability',
        icon: IdCard ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Property Accountability Receipt Record',
        href: '/PropertyReceipt',
        icon: Archive,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Property Return Slip Record',
        href: '/PropertyReturnSlip',
        icon: Archive,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Invent Custodian Slip',
        href: '/CustodianSlip',
        icon: ReceiptText ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Invent Custodian Slip Description',
        href: '/CustodianSlipDescrp',
        icon: ReceiptText ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Invent Furniture Fixtures',
        href: '/FurnitureFixtures',
        icon: Armchair ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Organization Chart',
        href: '/Organization',
        icon: Building2 ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Trip Ticket Vehicle',
        href: '/TripTicketVehicle',
        icon: Ticket ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Trip Ticket Driver',
        href: '/TripTicketDriver',
        icon: Ticket ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Trip Ticket Record',
        href: '/TripTicket',
        icon: Ticket ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Trip Ticket Passenger',
        href: '/TripTicketPassenger',
        icon: Ticket ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Trip Ticket Approval Log',
        href: '/TripTicketApproval',
        icon: Ticket ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Trip Ticket Fuel Record',
        href: '/TripTicketFuel',
        icon: Ticket ,
        roles: ['super_admin','inventory_user']
    },
    {
        title: 'Trip Ticket Maintenance',
        href: '/TripTicketMaintenance',
        icon: Ticket ,
        roles: ['super_admin','inventory_user']
    }

];

const footerNavItems: NavItem[] = [

];

// Access the authenticated user's role from Inertia props safely
const page = usePage();
const userRole = computed(() => {
    // Explicitly casting page.props to an expected structure inline to avoid errors
    const props = page.props as unknown as { auth?: { user?: { role?: string } } };
    return props.auth?.user?.role || null;
});

// Filter navigation items based on the user's role
const filteredNavItems = computed(() => {
    return mainNavItems.filter((item) => {  
        // If no roles are defined, show the item to everyone
        if (!item.roles) return true;
        // Check if the user's role is allowed
        return userRole.value ? item.roles.includes(userRole.value) : false;
    });
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="filteredNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>