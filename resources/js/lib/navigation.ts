import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Boxes,
    Building2,
    ContactRound,
    LayoutGrid,
    ListTree,
    Pill,
    ScrollText,
    SlidersHorizontal,
    Stethoscope,
    Truck,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as auditLog } from '@/routes/admin/audit-log';
import { index as branches } from '@/routes/admin/branches';
import { edit as storeSettings } from '@/routes/admin/settings';
import { index as users } from '@/routes/admin/users';
import { index as customers } from '@/routes/catalog/customers';
import { index as lookups } from '@/routes/catalog/lookups';
import { index as prescribers } from '@/routes/catalog/prescribers';
import { index as products } from '@/routes/catalog/products';
import { index as suppliers } from '@/routes/catalog/suppliers';
import { index as stock } from '@/routes/stock';

export type AppNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    /** Shown when the user has any of these permissions; omit for everyone. */
    permission?: string | string[];
};

export type AppNavSection = {
    title: string;
    items: AppNavItem[];
};

/**
 * The main menu. Items the user lacks permission for are hidden by
 * <AppSidebar>; the server still refuses the pages themselves.
 */
export const navigation: AppNavSection[] = [
    {
        title: 'Overview',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Inventory',
        items: [
            {
                title: 'Stock on hand',
                href: stock(),
                icon: Boxes,
                permission: 'stock.view',
            },
        ],
    },
    {
        title: 'Catalog',
        items: [
            {
                title: 'Products',
                href: products(),
                icon: Pill,
                permission: 'products.view',
            },
            {
                title: 'Lookups',
                href: lookups('generic-names'),
                icon: ListTree,
                permission: 'products.manage',
            },
            {
                title: 'Suppliers',
                href: suppliers(),
                icon: Truck,
                permission: 'suppliers.manage',
            },
            {
                title: 'Customers',
                href: customers(),
                icon: ContactRound,
                permission: 'customers.manage',
            },
            {
                title: 'Prescribers',
                href: prescribers(),
                icon: Stethoscope,
                permission: 'prescribers.manage',
            },
        ],
    },
    {
        title: 'Administration',
        items: [
            {
                title: 'Users',
                href: users(),
                icon: Users,
                permission: 'users.manage',
            },
            {
                title: 'Branches',
                href: branches(),
                icon: Building2,
                permission: 'branches.manage',
            },
            {
                title: 'Store settings',
                href: storeSettings(),
                icon: SlidersHorizontal,
                permission: 'settings.manage',
            },
            {
                title: 'Audit log',
                href: auditLog(),
                icon: ScrollText,
                permission: 'audit.view',
            },
        ],
    },
];
