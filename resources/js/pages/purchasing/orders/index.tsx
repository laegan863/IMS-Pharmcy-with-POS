import { Head, Link, router } from '@inertiajs/react';
import { ListChecks, Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import type { Option } from '@/components/form/select-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatMoney } from '@/lib/format';
import { reorder } from '@/routes/purchasing';
import { create, index, show } from '@/routes/purchasing/orders';
import type { Column, Paginated, TableState } from '@/types/table';

type OrderRow = {
    id: number;
    number: string;
    supplier: string;
    status: string;
    status_label: string;
    expected_on: string | null;
    total: string;
    created_at: string;
};

const columns: Column<OrderRow>[] = [
    {
        key: 'number',
        header: 'Number',
        sortable: true,
        cell: (row) => row.number,
    },
    { key: 'supplier', header: 'Supplier', cell: (row) => row.supplier },
    {
        key: 'created_at',
        header: 'Date',
        sortable: true,
        cell: (row) => formatDate(row.created_at),
    },
    {
        key: 'expected_on',
        header: 'Expected',
        sortable: true,
        cell: (row) => formatDate(row.expected_on),
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => (
            <DocumentStatusBadge status={row.status} label={row.status_label} />
        ),
    },
    {
        key: 'total',
        header: 'Total',
        align: 'right',
        cell: (row) => formatMoney(row.total),
    },
];

export default function OrdersIndex({
    rows,
    state,
    statuses,
}: {
    rows: Paginated<OrderRow>;
    state: TableState;
    statuses: Option[];
}) {
    const can = useCan();

    return (
        <>
            <Head title="Purchase orders" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Purchase orders"
                    actions={
                        can('purchase-orders.manage') && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={reorder()}>
                                        <ListChecks /> Reorder list
                                    </Link>
                                </Button>
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus /> New order
                                    </Link>
                                </Button>
                            </>
                        )
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Order number or supplier"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statuses',
                            options: statuses,
                        },
                    ]}
                    onRowClick={(row) => router.visit(show(row.id))}
                />
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
