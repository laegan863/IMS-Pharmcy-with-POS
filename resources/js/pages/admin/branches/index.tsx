import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/admin/branches';
import type { Column, Paginated, TableState } from '@/types/table';

type BranchRow = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    is_active: boolean;
    users_count: number;
    terminals_count: number;
};

const columns: Column<BranchRow>[] = [
    { key: 'code', header: 'Code', sortable: true, cell: (row) => row.code },
    {
        key: 'name',
        header: 'Name',
        sortable: true,
        cell: (row) => (
            <div>
                <div className="font-medium">{row.name}</div>
                {row.address && (
                    <div className="text-xs text-muted-foreground">
                        {row.address}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'terminals',
        header: 'Terminals',
        align: 'right',
        cell: (row) => row.terminals_count,
    },
    {
        key: 'users',
        header: 'Users',
        align: 'right',
        cell: (row) => row.users_count,
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) =>
            row.is_active ? (
                <StatusBadge tone="success">Active</StatusBadge>
            ) : (
                <StatusBadge>Closed</StatusBadge>
            ),
    },
];

export default function BranchesIndex({
    rows,
    state,
}: {
    rows: Paginated<BranchRow>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Branches" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Branches"
                    description="Store locations and their POS terminals"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New branch
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Search code or name"
                    onRowClick={(row) => router.visit(edit(row.id))}
                />
            </div>
        </>
    );
}

BranchesIndex.layout = {
    breadcrumbs: [{ title: 'Branches', href: index() }],
};
