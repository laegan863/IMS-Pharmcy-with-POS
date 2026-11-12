import { Head, router } from '@inertiajs/react';
import { ExpiryBadge } from '@/components/expiry-badge';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney, formatNumber } from '@/lib/format';
import { batch as batchRoute, index } from '@/routes/stock';

type BatchRow = {
    batch_id: number;
    lot_number: string;
    expires_on: string;
    days_to_expiry: number;
    status: string;
    sellable: boolean;
    quantity: number;
    unit_cost: string;
    supplier: string | null;
};

type Props = {
    product: {
        id: number;
        sku: string;
        name: string;
        base_unit: string | null;
        average_cost: string;
        reorder_point: number;
    };
    batches: BatchRow[];
};

export default function StockProduct({ product, batches }: Props) {
    const total = batches.reduce((sum, batch) => sum + batch.quantity, 0);

    return (
        <>
            <Head title={product.name} />
            <div className="px-4 py-6">
                <PageHeader
                    title={product.name}
                    description={`${product.sku} · average cost ${formatMoney(product.average_cost)} per ${product.base_unit ?? 'unit'} · batches in sale order (earliest expiry first)`}
                />
                <div className="max-w-5xl rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Lot</TableHead>
                                <TableHead>Expiry</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead className="text-right">
                                    Unit cost
                                </TableHead>
                                <TableHead className="text-right">
                                    Quantity
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {batches.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-16 text-center text-muted-foreground"
                                    >
                                        No stock in this branch.
                                    </TableCell>
                                </TableRow>
                            )}
                            {batches.map((batch) => (
                                <TableRow
                                    key={batch.batch_id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(batchRoute(batch.batch_id))
                                    }
                                >
                                    <TableCell className="font-medium">
                                        {batch.lot_number}
                                    </TableCell>
                                    <TableCell>
                                        <ExpiryBadge date={batch.expires_on} />
                                    </TableCell>
                                    <TableCell>
                                        {batch.sellable ? (
                                            <StatusBadge tone="success">
                                                Sellable
                                            </StatusBadge>
                                        ) : (
                                            <StatusBadge tone="danger">
                                                {batch.status === 'active'
                                                    ? 'Expired'
                                                    : batch.status}
                                            </StatusBadge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {batch.supplier ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(batch.unit_cost)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(batch.quantity)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colSpan={5}>Total</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatNumber(total)} {product.base_unit}
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </div>
            </div>
        </>
    );
}

StockProduct.layout = {
    breadcrumbs: [{ title: 'Stock on hand', href: index() }],
};
