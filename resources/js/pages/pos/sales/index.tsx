import { Head, Link, router } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatMoney } from '@/lib/format';
import { screen } from '@/routes/pos';
import { index, show } from '@/routes/pos/sales';
import type { Column, Paginated, TableState } from '@/types/table';

type SaleRow = {
    id: number;
    number: string;
    completed_at: string;
    cashier: string;
    terminal: string;
    discount_type: string;
    status: string;
    total: string;
};

const columns: Column<SaleRow>[] = [
    {
        key: 'number',
        header: 'Invoice',
        sortable: true,
        cell: (row) => row.number,
    },
    {
        key: 'completed_at',
        header: 'Date',
        sortable: true,
        cell: (row) => formatDateTime(row.completed_at),
    },
    {
        key: 'cashier',
        header: 'Cashier',
        cell: (row) => `${row.cashier} · ${row.terminal}`,
    },
    {
        key: 'discount',
        header: 'Discount',
        cell: (row) =>
            row.discount_type === 'none' ? (
                '—'
            ) : (
                <StatusBadge tone="info">
                    {row.discount_type === 'senior' ? 'Senior' : 'PWD'}
                </StatusBadge>
            ),
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => <DocumentStatusBadge status={row.status} />,
    },
    {
        key: 'total',
        header: 'Total',
        sortable: true,
        align: 'right',
        cell: (row) => formatMoney(row.total),
    },
];

export default function SalesIndex({
    rows,
    state,
}: {
    rows: Paginated<SaleRow>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Invoices" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Sales invoices"
                    description="Completed sales in this branch. Open one to reprint, void or refund."
                    actions={
                        <Button asChild>
                            <Link href={screen()}>
                                <ShoppingCart /> Open POS
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Invoice number or discount holder"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statuses',
                            options: [
                                { value: 'completed', label: 'Completed' },
                                { value: 'refunded', label: 'Refunded' },
                                { value: 'voided', label: 'Voided' },
                            ],
                        },
                        {
                            name: 'discount',
                            label: 'Discounts',
                            options: [
                                { value: 'senior', label: 'Senior citizen' },
                                { value: 'pwd', label: 'PWD' },
                                { value: 'none', label: 'No discount' },
                            ],
                        },
                    ]}
                    onRowClick={(row) => router.visit(show(row.id))}
                />
            </div>
        </>
    );
}

SalesIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: index() }],
};
