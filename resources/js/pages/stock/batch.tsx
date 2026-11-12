import { Head, Link, router } from '@inertiajs/react';
import { ShieldAlert, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { show as showSale } from '@/routes/pos/sales';
import { index, product as productRoute } from '@/routes/stock';
import { quarantine, release } from '@/routes/stock/batches';

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
    soldTo: {
        invoice: string;
        sale_id: number;
        date: string;
        quantity: number;
        customer: string | null;
        phone: string | null;
    }[];
    canQuarantine: boolean;
};

export default function StockBatch({
    batch,
    movements,
    soldTo,
    canQuarantine,
}: Props) {
    const [quarantining, setQuarantining] = useState(false);
    const [reason, setReason] = useState('');

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

                {canQuarantine && (
                    <div className="mt-6 flex gap-2">
                        {batch.status === 'quarantined' ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        release(batch.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ShieldCheck /> Release from quarantine
                            </Button>
                        ) : (
                            batch.status !== 'disposed' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setQuarantining(true)}
                                >
                                    <ShieldAlert /> Quarantine this lot
                                </Button>
                            )
                        )}
                    </div>
                )}

                <section className="mt-10 max-w-5xl">
                    <h2 className="mb-1 font-medium">Recall trace</h2>
                    <p className="mb-2 text-sm text-muted-foreground">
                        Every invoice, in any branch, that took units of this
                        lot.
                    </p>
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Invoice</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead className="text-right">
                                        Units kept
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {soldTo.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-14 text-center text-muted-foreground"
                                        >
                                            Not sold yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {soldTo.map((sale, i) => (
                                    <TableRow key={i}>
                                        <TableCell>
                                            <Link
                                                href={showSale(sale.sale_id)}
                                                className="underline underline-offset-4"
                                            >
                                                {sale.invoice}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {formatDateTime(sale.date)}
                                        </TableCell>
                                        <TableCell>
                                            {sale.customer ?? 'Walk-in'}
                                        </TableCell>
                                        <TableCell>
                                            {sale.phone ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatNumber(sale.quantity)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <QuarantineDialog
                    open={quarantining}
                    reason={reason}
                    onReasonChange={setReason}
                    onClose={() => setQuarantining(false)}
                    onConfirm={() =>
                        router.post(
                            quarantine(batch.id).url,
                            { reason },
                            {
                                preserveScroll: true,
                                onSuccess: () => setQuarantining(false),
                            },
                        )
                    }
                />
            </div>
        </>
    );
}

StockBatch.layout = {
    breadcrumbs: [{ title: 'Stock on hand', href: index() }],
};

/** Asks for the quarantine reason before taking the lot off sale. */
function QuarantineDialog({
    open,
    reason,
    onReasonChange,
    onClose,
    onConfirm,
}: {
    open: boolean;
    reason: string;
    onReasonChange: (reason: string) => void;
    onClose: () => void;
    onConfirm: () => void;
}) {
    return (
        <ConfirmDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Quarantine this lot?"
            description="It cannot be sold or issued in any branch until released."
            confirmLabel="Quarantine"
            destructive
            onConfirm={onConfirm}
        >
            <Input
                aria-label="Reason"
                placeholder="Reason, e.g. FDA recall notice no."
                value={reason}
                onChange={(event) => onReasonChange(event.target.value)}
            />
        </ConfirmDialog>
    );
}
