import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/format';
import { create, index, show } from '@/routes/stock/adjustments';
import type { Column, Paginated, TableState } from '@/types/table';

type Row = {
    id: number;
    number: string | null;
    status: string;
    reason: string;
    value: string;
    created_by: string;
    created_at: string;
};

const columns: Column<Row>[] = [
    {
        key: 'number',
        header: 'Number',
        sortable: true,
        cell: (row) =>
            row.number ?? <span className="text-muted-foreground">Draft</span>,
    },
    {
        key: 'created_at',
        header: 'Date',
        sortable: true,
        cell: (row) => formatDate(row.created_at),
    },
    { key: 'reason', header: 'Reason', cell: (row) => row.reason },
    { key: 'created_by', header: 'By', cell: (row) => row.created_by },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => <DocumentStatusBadge status={row.status} />,
    },
    {
        key: 'value',
        header: 'Value at cost',
        align: 'right',
        cell: (row) => formatMoney(row.value),
    },
];

export default function AdjustmentsIndex({
    rows,
    state,
    type,
}: {
    rows: Paginated<Row>;
    state: TableState;
    type: 'adjustment' | 'disposal';
}) {
    const title = type === 'disposal' ? 'Disposals' : 'Stock adjustments';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description={
                        type === 'disposal'
                            ? 'Expired or damaged stock destroyed, with method and witness.'
                            : 'Corrections to stock with a reason code. Staff draft; a pharmacist or the owner posts.'
                    }
                    actions={
                        <Button asChild>
                            <Link href={create(type)}>
                                <Plus /> New{' '}
                                {type === 'disposal'
                                    ? 'disposal'
                                    : 'adjustment'}
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Number or note"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statuses',
                            options: [
                                { value: 'draft', label: 'Draft' },
                                { value: 'posted', label: 'Posted' },
                            ],
                        },
                    ]}
                    onRowClick={(row) => router.visit(show(row.id))}
                />
            </div>
        </>
    );
}

AdjustmentsIndex.layout = {
    breadcrumbs: [{ title: 'Stock adjustments', href: index('adjustment') }],
};
