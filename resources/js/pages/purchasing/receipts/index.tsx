import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/format';
import { create, index, show } from '@/routes/purchasing/receipts';
import type { Column, Paginated, TableState } from '@/types/table';

type ReceiptRow = {
    id: number;
    number: string | null;
    supplier: string;
    supplier_invoice_number: string | null;
    purchase_order: string | null;
    status: string;
    total: string;
    posted_at: string | null;
    created_at: string;
};

const columns: Column<ReceiptRow>[] = [
    {
        key: 'number',
        header: 'Number',
        sortable: true,
        cell: (row) =>
            row.number ?? <span className="text-muted-foreground">Draft</span>,
    },
    { key: 'supplier', header: 'Supplier', cell: (row) => row.supplier },
    {
        key: 'invoice',
        header: 'Supplier invoice',
        cell: (row) => row.supplier_invoice_number ?? '—',
    },
    { key: 'po', header: 'PO', cell: (row) => row.purchase_order ?? 'Direct' },
    {
        key: 'created_at',
        header: 'Date',
        sortable: true,
        cell: (row) => formatDate(row.posted_at ?? row.created_at),
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => <DocumentStatusBadge status={row.status} />,
    },
    {
        key: 'total',
        header: 'Total',
        align: 'right',
        cell: (row) => formatMoney(row.total),
    },
];

export default function ReceiptsIndex({
    rows,
    state,
}: {
    rows: Paginated<ReceiptRow>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Goods receipts" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Goods receipts"
                    description="Deliveries received. Posting adds the stock."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Receive without PO
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Receipt, supplier invoice or supplier"
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

ReceiptsIndex.layout = {
    breadcrumbs: [{ title: 'Goods receipts', href: index() }],
};
