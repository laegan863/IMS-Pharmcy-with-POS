import { Head, Link } from '@inertiajs/react';
import { ExpiryBadge } from '@/components/expiry-badge';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatMoney, formatNumber } from '@/lib/format';
import { index, product as productRoute } from '@/routes/stock';

type Movement = {
    id: number;
    at: string;
    type: string;
    quantity: number;
    balance: number;
    reference: string | null;
    notes: string | null;
    user: string | null;
};

type Props = {
    batch: {
        id: number;
        lot_number: string;
        expires_on: string;
        status: string;
        status_reason: string | null;
        unit_cost: string;
        supplier: string | null;
        product: { id: number; name: string; base_unit: string | null };
    };
    movements: Movement[];
};

export default function StockBatch({ batch, movements }: Props) {
    return (
        <>
            <Head title={`Lot ${batch.lot_number}`} />
            <div className="px-4 py-6">
                <PageHeader
                    title={`Lot ${batch.lot_number}`}
                    description={batch.product.name}
                />
                <dl className="mb-6 grid max-w-3xl grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt className="text-muted-foreground">Expiry</dt>
                        <dd>
                            <ExpiryBadge date={batch.expires_on} />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Status</dt>
                        <dd>
                            <StatusBadge
                                tone={
                                    batch.status === 'active'
                                        ? 'success'
                                        : 'danger'
                                }
                            >
                                {batch.status}
                            </StatusBadge>
                            {batch.status_reason && (
                                <div className="text-xs text-muted-foreground">
                                    {batch.status_reason}
                                </div>
                            )}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Cost per {batch.product.base_unit ?? 'unit'}
                        </dt>
                        <dd>{formatMoney(batch.unit_cost)}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Supplier</dt>
                        <dd>{batch.supplier ?? '—'}</dd>
                    </div>
                </dl>

                <h2 className="mb-2 font-medium">
                    Movements in this branch (newest first)
                </h2>
                <div className="max-w-5xl rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>When</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead>By</TableHead>
                                <TableHead className="text-right">
                                    Change
                                </TableHead>
                                <TableHead className="text-right">
                                    Balance
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {movements.map((movement) => (
                                <TableRow key={movement.id}>
                                    <TableCell>
                                        {formatDateTime(movement.at)}
                                    </TableCell>
                                    <TableCell>
                                        {movement.type}
                                        {movement.notes && (
                                            <div className="text-xs whitespace-normal text-muted-foreground">
                                                {movement.notes}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {movement.reference ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {movement.user ?? '—'}
                                    </TableCell>
                                    <TableCell
                                        className={`text-right tabular-nums ${movement.quantity < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'}`}
                                    >
                                        {movement.quantity > 0 ? '+' : ''}
                                        {formatNumber(movement.quantity)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(movement.balance)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                <p className="mt-4 text-sm">
                    <Link
                        href={productRoute(batch.product.id)}
                        className="underline underline-offset-4"
                    >
                        All batches of this product
                    </Link>
                </p>
            </div>
        </>
    );
}

StockBatch.layout = {
    breadcrumbs: [{ title: 'Stock on hand', href: index() }],
};
