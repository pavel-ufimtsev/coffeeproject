<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CornerUpRight,
    Flame,
    FolderTree,
    LayoutGrid,
    Layers,
    Mail,
    Package,
    RefreshCw,
    ShoppingBag,
    SlidersHorizontal,
    Store,
    TicketPercent,
    Truck,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { home } from '@/routes';
import { dashboard } from '@/routes/admin';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as collectionsIndex } from '@/routes/admin/collections';
import { edit as deliveryEdit } from '@/routes/admin/delivery';
import { edit as emailsEdit } from '@/routes/admin/emails';
import { index as filtersIndex } from '@/routes/admin/filter-groups';
import { index as ordersIndex } from '@/routes/admin/orders';
import { index as productsIndex } from '@/routes/admin/products';
import { index as promoCodesIndex } from '@/routes/admin/promo-codes';
import { index as redirectsIndex } from '@/routes/admin/redirects';
import { index as retailcrmIndex } from '@/routes/admin/retailcrm';
import { index as roastersIndex } from '@/routes/admin/roasters';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'Orders', href: ordersIndex(), icon: ShoppingBag },
    { title: 'Promo codes', href: promoCodesIndex(), icon: TicketPercent },
    { title: 'Delivery', href: deliveryEdit(), icon: Truck },
    { title: 'Emails', href: emailsEdit(), icon: Mail },
    { title: 'Products', href: productsIndex(), icon: Package },
    { title: 'Categories', href: categoriesIndex(), icon: FolderTree },
    { title: 'Filters', href: filtersIndex(), icon: SlidersHorizontal },
    { title: 'Collections', href: collectionsIndex(), icon: Layers },
    { title: 'Roasters', href: roastersIndex(), icon: Flame },
    { title: 'retailCRM', href: retailcrmIndex(), icon: RefreshCw },
    { title: 'Redirects', href: redirectsIndex(), icon: CornerUpRight },
    {
        title: 'Users',
        href: usersIndex(),
        icon: Users,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'View shop',
        href: home(),
        icon: Store,
    },
];
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
