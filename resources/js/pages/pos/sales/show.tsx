import { Head, useForm } from '@inertiajs/react';
import { Ban, Printer, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { SupervisorApprovalDialog } from '@/components/supervisor-approval-dialog';
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
import { formatDateTime, formatMoney } from '@/lib/format';
import { index, receipt, refund, voidMethod } from '@/routes/pos/sales';

type Line = {
    id: number;
    description: string;
    quantity: number;
    quantity_base: number;
    refundable_base: number;
    unit_price: string;
    discount_amount: string;
    line_total: string;
};

type Sale = {
    id: number;
    number: string;
    status: string;
    completed_at: string;
    cashier: string;
    terminal: string;
    customer: string | null;
    discount_type: string;
    discount_holder_name: string | null;
    totals: Record<string, string>;
    lines: Line[];
    payments: { method: string; amount: string; reference: string | null }[];
    adjustments: {
        number: string;
        type: string;
        amount: string;
        reason: string;
        approved_by: string;
        created_at: string;
    }[];
};

export default function SaleShow({
    sale,
    canAdjust,
}: {
    sale: Sale;
    canAdjust: boolean;
}) {
    const [mode, setMode] = useState<'void' | 'refund' | null>(null);
    const form = useForm({
        reason: '',
        approver: '',
        approver_pin: '',
        quantities: Object.fromEntries(
            sale.lines.map((line) => [line.id, 0]),
        ) as Record<number, number>,
    });

    const submit = (approval: { approver: string; approver_pin: string }) => {
        form.transform((data) => ({ ...data, ...approval }));
        const url =
            mode === 'void' ? voidMethod(sale.id).url : refund(sale.id).url;
        form.post(url, {
            preserveScroll: true,
            onSuccess: () => setMode(null),
        });
    };

    const t = sale.totals;

    return (
        <>
            <Head title={`Invoice ${sale.number}`} />
            <div className="max-w-5xl px-4 py-6">
                <PageHeader
                    title={`Invoice ${sale.number}`}
                    description={`${formatDateTime(sale.completed_at)} · ${sale.cashier} · ${sale.terminal}${sale.discount_type !== 'none' ? ` · ${sale.discount_type === 'senior' ? 'Senior' : 'PWD'}: ${sale.discount_holder_name}` : ''}`}
                    actions={
                        <>
                            <DocumentStatusBadge status={sale.status} />
                            <Button variant="outline" asChild>
                                <a
                                    href={receipt(sale.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer /> Reprint
                                </a>
                            </Button>
                            {canAdjust && sale.status === 'completed' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setMode('void')}
                                >
                                    <Ban /> Void
                                </Button>
                            )}
                            {canAdjust && sale.status !== 'voided' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setMode('refund')}
                                >
                                    <Undo2 /> Refund
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Item</TableHead>
                                <TableHead className="text-right">
                                    Qty
                                </TableHead>
                                <TableHead className="text-right">
                                    Price
                                </TableHead>
                                <TableHead className="text-right">
                                    Discount
                                </TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sale.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell className="whitespace-normal">
                                        {line.description}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {line.quantity}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(line.unit_price)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(line.discount_amount)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(line.line_total)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <dl className="mt-4 ml-auto grid max-w-sm grid-cols-2 gap-1 text-sm">
                    <dt className="text-muted-foreground">VATable sales</dt>
                    <dd className="text-right">
                        {formatMoney(t.vatable_sales)}
                    </dd>
                    <dt className="text-muted-foreground">VAT</dt>
                    <dd className="text-right">{formatMoney(t.vat_amount)}</dd>
                    <dt className="text-muted-foreground">VAT-exempt sales</dt>
                    <dd className="text-right">
                        {formatMoney(t.vat_exempt_sales)}
                    </dd>
                    <dt className="text-muted-foreground">Zero-rated sales</dt>
                    <dd className="text-right">
                        {formatMoney(t.zero_rated_sales)}
                    </dd>
                    <dt className="text-muted-foreground">
                        Senior/PWD discount
                    </dt>
                    <dd className="text-right">
                        {formatMoney(t.statutory_discount)}
                    </dd>
                    <dt className="font-medium">Total</dt>
                    <dd className="text-right font-medium">
                        {formatMoney(t.total)}
                    </dd>
                    {sale.payments.map((payment, i) => (
                        <div key={i} className="contents">
                            <dt className="text-muted-foreground">
                                {payment.method}
                                {payment.reference && ` #${payment.reference}`}
                            </dt>
                            <dd className="text-right">
                                {formatMoney(payment.amount)}
                            </dd>
                        </div>
                    ))}
                    <dt className="text-muted-foreground">Change</dt>
                    <dd className="text-right">{formatMoney(t.change_due)}</dd>
                </dl>

                {sale.adjustments.length > 0 && (
                    <section className="mt-8">
                        <h2 className="mb-2 font-medium">Voids and refunds</h2>
                        <ul className="space-y-1 text-sm">
                            {sale.adjustments.map((adjustment) => (
                                <li key={adjustment.number}>
                                    {adjustment.number} · {adjustment.type}{' '}
                                    {formatMoney(adjustment.amount)} ·{' '}
                                    {adjustment.reason} · approved by{' '}
                                    {adjustment.approved_by} ·{' '}
                                    {formatDateTime(adjustment.created_at)}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>

            <SupervisorApprovalDialog
                open={mode !== null}
                onOpenChange={(open) => !open && setMode(null)}
                title={
                    mode === 'void'
                        ? `Void ${sale.number}`
                        : `Refund from ${sale.number}`
                }
                description={
                    mode === 'void'
                        ? 'The whole sale is cancelled with its own void number, and the stock goes back to its batches.'
                        : 'Choose how many base units of each item to refund. The amount follows what the customer paid.'
                }
                processing={form.processing}
                error={
                    form.errors.approver_pin ??
                    form.errors.reason ??
                    (form.errors as Record<string, string>).sale ??
                    (form.errors as Record<string, string>).lines
                }
                onApprove={submit}
            >
                <TextField
                    id="adjust-reason"
                    label="Reason"
                    value={form.data.reason}
                    onChange={(value) => form.setData('reason', value)}
                    error={form.errors.reason}
                    required
                />
                {mode === 'refund' &&
                    sale.lines.map((line) => (
                        <div
                            key={line.id}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <span className="flex-1">
                                {line.description}
                                <span className="block text-xs text-muted-foreground">
                                    up to {line.refundable_base} base units
                                </span>
                            </span>
                            <Input
                                aria-label={`Refund quantity for ${line.description}`}
                                inputMode="numeric"
                                className="w-20"
                                value={form.data.quantities[line.id]}
                                onChange={(event) =>
                                    form.setData('quantities', {
                                        ...form.data.quantities,
                                        [line.id]: Math.min(
                                            line.refundable_base,
                                            Math.max(
                                                0,
                                                Number(event.target.value) || 0,
                                            ),
                                        ),
                                    })
                                }
                            />
                        </div>
                    ))}
            </SupervisorApprovalDialog>
        </>
    );
}

SaleShow.layout = {
    breadcrumbs: [{ title: 'Invoices', href: index() }],
};
