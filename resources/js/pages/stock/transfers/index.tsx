import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/stock/transfers';
import type { Column, Paginated, TableState } from '@/types/table';

type Row = {
    id: number;
    number: string | null;
    status: string;
    from: string;
    to: string;
    direction: 'in' | 'out';
    created_at: string;
};

const columns: Column<Row>[] = [
    {
        key: 'number',
        header: 'Number',
        cell: (row) =>
            row.number ?? <span className="text-muted-foreground">Draft</span>,
    },
    {
        key: 'created_at',
        header: 'Date',
        sortable: true,
        cell: (row) => formatDate(row.created_at),
    },
    {
        key: 'direction',
        header: 'Direction',
        cell: (row) => (
            <StatusBadge tone={row.direction === 'in' ? 'info' : 'neutral'}>
                {row.direction === 'in' ? 'Incoming' : 'Outgoing'}
            </StatusBadge>
        ),
    },
    {
        key: 'route',
        header: 'From → to',
        cell: (row) => `${row.from} → ${row.to}`,
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => <DocumentStatusBadge status={row.status} />,
    },
];

export default function TransfersIndex({
    rows,
    state,
}: {
    rows: Paginated<Row>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Branch transfers" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Branch transfers"
                    description="Stock leaves this branch when dispatched and arrives when the other branch receives it."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New transfer
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Transfer number"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statuses',
                            options: [
                                { value: 'draft', label: 'Draft' },
                                { value: 'in_transit', label: 'In transit' },
                                { value: 'received', label: 'Received' },
                            ],
                        },
                    ]}
                    onRowClick={(row) => router.visit(show(row.id))}
                />
            </div>
        </>
    );
}

TransfersIndex.layout = {
    breadcrumbs: [{ title: 'Branch transfers', href: index() }],
};
