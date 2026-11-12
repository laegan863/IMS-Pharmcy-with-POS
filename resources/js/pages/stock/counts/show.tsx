import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/stock/counts';

type Line = {
    id: number;
    product: string;
    base_unit: string | null;
    lot_number: string;
    expires_on: string;
    system_quantity: number;
    counted_quantity: number | null;
    variance: number | null;
    unit_cost: string;
};

type Count = {
    id: number;
    number: string;
    status: string;
    scope: string;
    posted_at: string | null;
    lines: Line[];
};

export default function CountShow({
    count,
    canPost,
}: {
    count: Count;
    canPost: boolean;
}) {
    const posted = count.status === 'posted';
    const [confirm, setConfirm] = useState(false);
    const form = useForm({
        counts: Object.fromEntries(
            count.lines.map((line) => [line.id, line.counted_quantity ?? '']),
        ) as Record<number, number | string>,
        action: 'save',
    });

    const send = (action: 'save' | 'review' | 'post') => {
        form.transform((data) => ({
            ...data,
            action,
            counts: Object.fromEntries(
                Object.entries(data.counts).map(([id, value]) => [
                    id,
                    value === '' ? null : Number(value),
                ]),
            ),
        }));
        form.put(update(count.id).url, {
            preserveScroll: true,
            onFinish: () => setConfirm(false),
        });
    };

    const varianceOf = (line: Line) => {
        const value = form.data.counts[line.id];

        return value === '' || value === undefined
            ? null
            : Number(value) - line.system_quantity;
    };

    const varianceValue = count.lines.reduce(
        (sum, line) => sum + (varianceOf(line) ?? 0) * Number(line.unit_cost),
        0,
    );
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title={`Count ${count.number}`} />
            <div className="px-4 py-6">
                <PageHeader
                    title={`Stock count ${count.number}`}
                    description={`${count.scope}. System quantities were frozen when counting started; sales since then are not counted twice.`}
                    actions={<DocumentStatusBadge status={count.status} />}
                />
                {(errors.lines || errors.status) && (
                    <p className="mb-4 text-sm text-destructive">
                        {errors.lines ?? errors.status}
                    </p>
                )}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Lot</TableHead>
                                <TableHead>Expiry</TableHead>
                                <TableHead className="text-right">
                                    System
                                </TableHead>
                                <TableHead>Counted</TableHead>
                                <TableHead className="text-right">
                                    Variance
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {count.lines.map((line) => {
                                const variance = varianceOf(line);

                                return (
                                    <TableRow key={line.id}>
                                        <TableCell className="whitespace-normal">
                                            {line.product}
                                        </TableCell>
                                        <TableCell>{line.lot_number}</TableCell>
                                        <TableCell>
                                            {formatDate(line.expires_on)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {line.system_quantity}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                {line.base_unit}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                aria-label={`Counted ${line.product} lot ${line.lot_number}`}
                                                inputMode="numeric"
                                                className="w-24"
                                                disabled={posted}
                                                value={
                                                    form.data.counts[line.id]
                                                }
                                                onChange={(event) =>
                                                    form.setData('counts', {
                                                        ...form.data.counts,
                                                        [line.id]:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right tabular-nums',
                                                variance !== null &&
                                                    variance < 0 &&
                                                    'text-red-600 dark:text-red-400',
                                                variance !== null &&
                                                    variance > 0 &&
                                                    'text-emerald-700 dark:text-emerald-400',
                                            )}
                                        >
                                            {variance === null
                                                ? '—'
                                                : variance > 0
                                                  ? `+${variance}`
                                                  : variance}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
                <p className="mt-3 text-sm">
                    Variance at cost: {formatMoney(varianceValue)}
                </p>
                {!posted && (
                    <div className="mt-4 flex gap-2">
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => send('save')}
                        >
                            Save counts
                        </Button>
                        {count.status === 'counting' && (
                            <Button
                                disabled={form.processing}
                                onClick={() => send('review')}
                            >
                                Mark reviewed
                            </Button>
                        )}
                        {canPost && (
                            <Button
                                disabled={form.processing}
                                onClick={() => setConfirm(true)}
                            >
                                Post variances
                            </Button>
                        )}
                    </div>
                )}
                <ConfirmDialog
                    open={confirm}
                    onOpenChange={setConfirm}
                    title="Post this count?"
                    description={`Stock is adjusted by the variances (${formatMoney(varianceValue)} at cost). This cannot be undone.`}
                    confirmLabel="Post"
                    processing={form.processing}
                    onConfirm={() => send('post')}
                />
            </div>
        </>
    );
}

CountShow.layout = {
    breadcrumbs: [{ title: 'Stock counts', href: index() }],
};
