import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Building2,
    LayoutGrid,
    ScrollText,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as auditLog } from '@/routes/admin/audit-log';
import { index as branches } from '@/routes/admin/branches';
import { edit as storeSettings } from '@/routes/admin/settings';
import { index as users } from '@/routes/admin/users';

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
