import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/admin/users';
import type { Column, Paginated, TableState } from '@/types/table';

type UserRow = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    role: string | null;
    branch: string | null;
    is_active: boolean;
    two_factor: boolean;
};

type Props = {
    rows: Paginated<UserRow>;
    state: TableState;
    roles: { value: string; label: string }[];
};

const columns: Column<UserRow>[] = [
    {
        key: 'name',
        header: 'Name',
        sortable: true,
        cell: (user) => (
            <div>
                <div className="font-medium">{user.name}</div>
                <div className="text-xs text-muted-foreground">
                    {user.email}
                </div>
            </div>
        ),
    },
    {
        key: 'username',
        header: 'Username',
        sortable: true,
        cell: (user) => user.username,
    },
    { key: 'role', header: 'Role', cell: (user) => user.role ?? '—' },
    { key: 'branch', header: 'Branch', cell: (user) => user.branch ?? '—' },
    {
        key: 'two_factor',
        header: '2FA',
        cell: (user) =>
            user.two_factor ? (
                <StatusBadge tone="success">On</StatusBadge>
            ) : (
                <StatusBadge>Off</StatusBadge>
            ),
    },
    {
        key: 'status',
        header: 'Status',
        cell: (user) =>
            user.is_active ? (
                <StatusBadge tone="success">Active</StatusBadge>
            ) : (
                <StatusBadge tone="danger">Deactivated</StatusBadge>
            ),
    },
];

export default function UsersIndex({ rows, state, roles }: Props) {
    return (
        <>
            <Head title="Users" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Users"
                    description="Staff accounts, roles and supervisor PINs"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New user
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(user) => user.id}
                    searchPlaceholder="Search name, username or email"
                    filters={[
                        { name: 'role', label: 'Roles', options: roles },
                        {
                            name: 'status',
                            label: 'Statuses',
                            options: [
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Deactivated' },
                            ],
                        },
                    ]}
                    onRowClick={(user) => router.visit(edit(user.id))}
                />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
