import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/catalog/prescribers';
import type { Column, Paginated, TableState } from '@/types/table';

type PrescriberRow = {
    id: number;
    name: string;
    prc_license_number: string | null;
    ptr_number: string | null;
    s2_license_number: string | null;
    specialty: string | null;
    is_active: boolean;
};

const columns: Column<PrescriberRow>[] = [
    {
        key: 'name',
        header: 'Name',
        sortable: true,
        cell: (row) => (
            <div>
                <div className="font-medium">{row.name}</div>
                {row.specialty && (
                    <div className="text-xs text-muted-foreground">
                        {row.specialty}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'prc',
        header: 'PRC no.',
        cell: (row) => row.prc_license_number ?? '—',
    },
    { key: 'ptr', header: 'PTR no.', cell: (row) => row.ptr_number ?? '—' },
    {
        key: 's2',
        header: 'S2',
        cell: (row) =>
            row.s2_license_number ? (
                <StatusBadge tone="warning">
                    {row.s2_license_number}
                </StatusBadge>
            ) : (
                '—'
            ),
    },
];

export default function PrescribersIndex({
    rows,
    state,
}: {
    rows: Paginated<PrescriberRow>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Prescribers" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Prescribers"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New prescriber
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Name or PRC number"
                    onRowClick={(row) => router.visit(edit(row.id))}
                />
            </div>
        </>
    );
}

PrescribersIndex.layout = {
    breadcrumbs: [{ title: 'Prescribers', href: index() }],
};
