import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { Field } from '@/components/form/field';
import { NativeSelect } from '@/components/form/native-select';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { LineItemEditor } from '@/components/line-item-editor';
import type { LineColumn } from '@/components/line-item-editor';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { TableCell, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, formatMoney } from '@/lib/format';
import { batch as batchRoute } from '@/routes/stock';
import { index, store, update } from '@/routes/purchasing/receipts';

type UnitOption = { id: number; name: string; conversion_factor: number };

type Line = {
    purchase_order_line_id: number | null;
    product_id: number;
    product_name: string;
    product_unit_id: number;
    units: UnitOption[];
    lot_number: string;
    expires_on: string;
    quantity: number | string;
    free_quantity: number | string;
    unit_cost: string;
    batch_id?: number | null;
};

type Receipt = {
    id: number | null;
    number: string | null;
    status: string;
    supplier_id: number;
    supplier?: string;
    purchase_order_id: number | null;
    purchase_order_number: string | null;
    supplier_invoice_number: string;
    supplier_invoice_date: string;
    notes: string;
    total?: string;
    posted_by?: string | null;
    posted_at?: string | null;
    lines: Line[];
};

type Props = {
    receipt: Receipt | null;
    suppliers: Option[];
    /** Shelf-life, cost-change and licence warnings for a saved draft. */
    warnings: string[];
};

const lineTotal = (line: Line) =>
    (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0);

export default function ReceiptForm({ receipt, suppliers, warnings }: Props) {
    const posted = receipt?.status === 'posted';
    const [confirmPost, setConfirmPost] = useState(false);

    const form = useForm({
        supplier_id: receipt ? String(receipt.supplier_id) : '',
        purchase_order_id: receipt?.purchase_order_id ?? null,
        supplier_invoice_number: receipt?.supplier_invoice_number ?? '',
        supplier_invoice_date: receipt?.supplier_invoice_date ?? '',
        notes: receipt?.notes ?? '',
        lines: (receipt?.lines ?? []) as Line[],
        post: false,
        acknowledge_warnings: false,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const serverWarnings = (form.errors as Record<string, string | string[]>)
        .warnings;
    const allWarnings = [
        ...warnings,
        ...(Array.isArray(serverWarnings)
            ? serverWarnings
            : serverWarnings
              ? [serverWarnings]
              : []),
    ].filter((warning, i, list) => list.indexOf(warning) === i);

    const send = (post: boolean) => {
        // Warnings are only acknowledged once the user has seen them; if the
        // server finds new ones it saves the draft and shows them first.
        form.transform((data) => ({
            ...data,
            post,
            acknowledge_warnings: post && allWarnings.length > 0,
        }));

        const options = {
            preserveScroll: true,
            onFinish: () => setConfirmPost(false),
        };

        if (receipt?.id) {
            form.put(update(receipt.id).url, options);
        } else {
            form.post(store().url, options);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        send(false);
    };

    const columns: LineColumn<Line>[] = [
        {
            key: 'product_id',
            header: 'Product',
            className: 'min-w-56 whitespace-normal',
            cell: (line) => (
                <div>
                    <span className="font-medium">{line.product_name}</span>
                    {posted && line.batch_id && (
                        <Link
                            href={batchRoute(line.batch_id)}
                            className="block text-xs text-muted-foreground underline"
                        >
                            View batch
                        </Link>
                    )}
                </div>
            ),
        },
        {
            key: 'product_unit_id',
            header: 'Unit',
            cell: (line, _i, set) => (
                <NativeSelect
                    aria-label="Unit"
                    value={line.product_unit_id}
                    disabled={posted}
                    onChange={(event) =>
                        set({ product_unit_id: Number(event.target.value) })
                    }
                >
                    {line.units.map((unit) => (
                        <option key={unit.id} value={unit.id}>
                            {unit.name}
                            {unit.conversion_factor > 1 &&
                                ` (${unit.conversion_factor})`}
                        </option>
                    ))}
                </NativeSelect>
            ),
        },
        {
            key: 'lot_number',
            header: 'Batch no.',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Batch number"
                    className="w-32"
                    value={line.lot_number}
                    disabled={posted}
                    onChange={(event) =>
                        set({ lot_number: event.target.value })
                    }
                />
            ),
        },
        {
            key: 'expires_on',
            header: 'Expiry',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Expiry date"
                    type="date"
                    className="w-40"
                    value={line.expires_on}
                    disabled={posted}
                    onChange={(event) =>
                        set({ expires_on: event.target.value })
                    }
                />
            ),
        },
        {
            key: 'quantity',
            header: 'Qty',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Quantity"
                    inputMode="numeric"
                    className="w-20"
                    value={line.quantity}
                    disabled={posted}
                    onChange={(event) => set({ quantity: event.target.value })}
                />
            ),
        },
        {
            key: 'free_quantity',
            header: 'Free',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Free quantity"
                    inputMode="numeric"
                    className="w-16"
                    value={line.free_quantity}
                    disabled={posted}
                    onChange={(event) =>
                        set({ free_quantity: event.target.value })
                    }
                />
            ),
        },
        {
            key: 'unit_cost',
            header: 'Unit cost',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Unit cost"
                    inputMode="decimal"
                    className="w-28"
                    value={line.unit_cost}
                    disabled={posted}
                    onChange={(event) => set({ unit_cost: event.target.value })}
                />
            ),
        },
        {
            key: 'line_total',
            header: 'Total',
            className: 'text-right',
            cell: (line) => formatMoney(lineTotal(line)),
        },
    ];

    const total = form.data.lines.reduce(
        (sum, line) => sum + lineTotal(line),
        0,
    );
    const title = receipt?.number
        ? `Goods receipt ${receipt.number}`
        : receipt?.id
          ? 'Goods receipt (draft)'
          : 'Receive goods';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description={
                        posted
                            ? `Posted by ${receipt?.posted_by ?? '—'} ${formatDateTime(receipt?.posted_at)}. Posted receipts cannot be changed.`
                            : receipt?.purchase_order_number
                              ? `Against purchase order ${receipt.purchase_order_number}`
                              : 'Direct receipt, without a purchase order'
                    }
                    actions={
                        receipt && (
                            <DocumentStatusBadge status={receipt.status} />
                        )
                    }
                />

                {!posted && allWarnings.length > 0 && (
                    <Alert className="mb-6 max-w-6xl">
                        <AlertTriangle />
                        <AlertTitle>Check before posting</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {allWarnings.map((warning) => (
                                    <li key={warning}>{warning}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="max-w-6xl space-y-6">
                    <div className="grid gap-6 sm:grid-cols-3">
                        <SelectField
                            id="supplier_id"
                            label="Supplier"
                            value={form.data.supplier_id}
                            options={suppliers}
                            onChange={(value) =>
                                form.setData('supplier_id', value)
                            }
                            error={errors.supplier_id}
                            disabled={
                                posted || Boolean(form.data.purchase_order_id)
                            }
                            required
                        />
                        <TextField
                            id="supplier_invoice_number"
                            label="Supplier invoice no."
                            value={form.data.supplier_invoice_number}
                            onChange={(value) =>
                                form.setData('supplier_invoice_number', value)
                            }
                            error={errors.supplier_invoice_number}
                            disabled={posted}
                        />
                        <TextField
                            id="supplier_invoice_date"
                            label="Invoice date"
                            type="date"
                            value={form.data.supplier_invoice_date}
                            onChange={(value) =>
                                form.setData('supplier_invoice_date', value)
                            }
                            error={errors.supplier_invoice_date}
                            disabled={posted}
                        />
                    </div>

                    <LineItemEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        columns={columns}
                        errors={errors}
                        readOnly={posted}
                        pickerPlaceholder="Scan or search a product to receive…"
                        makeLine={(product, unitId) => {
                            const units = product.units.filter(
                                (unit) => unit.is_purchasable || unit.is_base,
                            );
                            const unit =
                                units.find((u) => u.id === unitId) ??
                                [...units].sort(
                                    (a, b) =>
                                        b.conversion_factor -
                                        a.conversion_factor,
                                )[0];

                            return unit
                                ? {
                                      purchase_order_line_id: null,
                                      product_id: product.id,
                                      product_name: product.name,
                                      product_unit_id: unit.id,
                                      units,
                                      lot_number: '',
                                      expires_on: '',
                                      quantity: 1,
                                      free_quantity: 0,
                                      unit_cost: '',
                                  }
                                : null;
                        }}
                        footer={
                            <TableRow>
                                <TableCell colSpan={8}>Receipt total</TableCell>
                                <TableCell className="text-right">
                                    {formatMoney(total)}
                                </TableCell>
                                {!posted && <TableCell />}
                            </TableRow>
                        }
                    />

                    <Field label="Notes" htmlFor="notes" error={errors.notes}>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            disabled={posted}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                    </Field>

                    <div className="flex gap-2">
                        {!posted && (
                            <>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={form.processing}
                                >
                                    Save draft
                                </Button>
                                <Button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => setConfirmPost(true)}
                                >
                                    {form.processing && <Spinner />}
                                    <Send /> Post receipt
                                </Button>
                            </>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Back</Link>
                        </Button>
                    </div>
                </form>

                <ConfirmDialog
                    open={confirmPost}
                    onOpenChange={setConfirmPost}
                    title="Post this receipt?"
                    description={
                        allWarnings.length > 0
                            ? `Posting adds the stock and cannot be undone. You are posting despite ${allWarnings.length} warning(s).`
                            : 'Posting adds the stock and cannot be undone; corrections are made with a supplier return or adjustment.'
                    }
                    confirmLabel="Post receipt"
                    processing={form.processing}
                    onConfirm={() => send(true)}
                />
            </div>
        </>
    );
}

ReceiptForm.layout = {
    breadcrumbs: [{ title: 'Goods receipts', href: index() }],
};
