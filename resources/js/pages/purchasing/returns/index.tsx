import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/format';
import { create, index, show } from '@/routes/purchasing/returns';
import type { Column, Paginated, TableState } from '@/types/table';

type ReturnRow = {
    id: number;
    number: string | null;
    supplier: string;
    reason: string;
    status: string;
    total: string;
    created_at: string;
};

const columns: Column<ReturnRow>[] = [
    {
        key: 'number',
        header: 'Number',
        sortable: true,
        cell: (row) =>
            row.number ?? <span className="text-muted-foreground">Draft</span>,
    },
    { key: 'supplier', header: 'Supplier', cell: (row) => row.supplier },
    { key: 'reason', header: 'Reason', cell: (row) => row.reason },
    {
        key: 'created_at',
        header: 'Date',
        sortable: true,
        cell: (row) => formatDate(row.created_at),
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => <DocumentStatusBadge status={row.status} />,
    },
    {
        key: 'total',
        header: 'Value at cost',
        align: 'right',
        cell: (row) => formatMoney(row.total),
    },
];

export default function ReturnsIndex({
    rows,
    state,
}: {
    rows: Paginated<ReturnRow>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Supplier returns" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Supplier returns"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New return
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Return number or supplier"
                    onRowClick={(row) => router.visit(show(row.id))}
                />
            </div>
        </>
    );
}

ReturnsIndex.layout = {
    breadcrumbs: [{ title: 'Supplier returns', href: index() }],
};
