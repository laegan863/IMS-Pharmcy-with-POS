import { Head, Link, router } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { ExpiryBadge } from '@/components/expiry-badge';
import type { Option } from '@/components/form/select-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatMoney, formatNumber } from '@/lib/format';
import { index, opening, product } from '@/routes/stock';
import type { Column, Paginated, TableState } from '@/types/table';

type StockRow = {
    id: number;
    sku: string;
    name: string;
    base_unit: string | null;
    on_hand: number;
    sellable: number;
    reorder_point: number;
    nearest_expiry: string | null;
    value: string;
};

const columns: Column<StockRow>[] = [
    { key: 'sku', header: 'SKU', sortable: true, cell: (row) => row.sku },
    {
        key: 'name',
        header: 'Product',
        cell: (row) => (
            <div className="max-w-md font-medium whitespace-normal">
                {row.name}
            </div>
        ),
    },
    {
        key: 'on_hand',
        header: 'On hand',
        sortable: true,
        align: 'right',
        cell: (row) => (
            <div>
                <span className="font-medium tabular-nums">
                    {formatNumber(row.on_hand)}
                </span>{' '}
                <span className="text-xs text-muted-foreground">
                    {row.base_unit}
                </span>
                {row.sellable < row.on_hand && (
                    <div className="text-xs text-muted-foreground">
                        {formatNumber(row.sellable)} sellable
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'reorder',
        header: 'Reorder at',
        align: 'right',
        cell: (row) =>
            row.sellable <= row.reorder_point ? (
                <StatusBadge tone="warning">
                    Low · {formatNumber(row.reorder_point)}
                </StatusBadge>
            ) : (
                formatNumber(row.reorder_point)
            ),
    },
    {
        key: 'nearest_expiry',
        header: 'Nearest expiry',
        sortable: true,
        cell: (row) => <ExpiryBadge date={row.nearest_expiry} />,
    },
    {
        key: 'value',
        header: 'Value at cost',
        align: 'right',
        cell: (row) => formatMoney(row.value),
    },
];

export default function StockIndex({
    rows,
    state,
    categories,
    alertDays,
}: {
    rows: Paginated<StockRow>;
    state: TableState;
    categories: Option[];
    alertDays: number;
}) {
    const can = useCan();

    return (
        <>
            <Head title="Stock on hand" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Stock on hand"
                    description="This branch, in base units. Expired and quarantined batches are on hand but not sellable."
                    actions={
                        can('stock.import') && (
                            <Button variant="outline" asChild>
                                <Link href={opening()}>
                                    <Upload /> Opening stock
                                </Link>
                            </Button>
                        )
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Brand, generic name, SKU or barcode"
                    filters={[
                        {
                            name: 'view',
                            label: 'Products',
                            options: [
                                { value: 'in_stock', label: 'In stock' },
                                { value: 'low', label: 'At or below reorder' },
                                {
                                    value: 'near_expiry',
                                    label: `Expiring within ${alertDays} days`,
                                },
                            ],
                        },
                        {
                            name: 'category',
                            label: 'Categories',
                            options: categories,
                        },
                    ]}
                    onRowClick={(row) => router.visit(product(row.id))}
                />
            </div>
        </>
    );
}

StockIndex.layout = {
    breadcrumbs: [{ title: 'Stock on hand', href: index() }],
};
