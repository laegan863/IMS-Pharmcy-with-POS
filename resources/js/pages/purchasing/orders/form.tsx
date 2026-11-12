import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, PackageCheck, XCircle } from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { TableCell, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, formatMoney } from '@/lib/format';
import { create as createReceipt } from '@/routes/purchasing/receipts';
import {
    approve,
    cancel,
    index,
    store,
    update,
} from '@/routes/purchasing/orders';

type UnitOption = { id: number; name: string; conversion_factor: number };

type Line = {
    product_id: number;
    product_name: string;
    product_unit_id: number;
    units: UnitOption[];
    quantity: number | string;
    unit_cost: string;
    received?: number;
};

type Order = {
    id: number;
    number: string;
    supplier_id: number;
    supplier: string;
    status: string;
    status_label: string;
    expected_on: string | null;
    notes: string | null;
    total: string;
    created_by: string | null;
    approved_by: string | null;
    approved_at: string | null;
    lines: Line[];
};

type Props = {
    order: Order | null;
    suppliers: Option[];
    can?: {
        edit: boolean;
        approve: boolean;
        cancel: boolean;
        receive: boolean;
    };
};

const lineTotal = (line: Line) =>
    (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0);

export default function OrderForm({ order, suppliers, can }: Props) {
    const editable = !order || (can?.edit ?? false);
    const [confirm, setConfirm] = useState<'approve' | 'cancel' | null>(null);
    const [processing, setProcessing] = useState(false);

    const form = useForm({
        supplier_id: order ? String(order.supplier_id) : '',
        expected_on: order?.expected_on ?? '',
        notes: order?.notes ?? '',
        lines: (order?.lines ?? []) as Line[],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (order) {
            form.put(update(order.id).url, { preserveScroll: true });
        } else {
            form.post(store().url);
        }
    };

    const runAction = (action: 'approve' | 'cancel') => {
        if (!order) {
            return;
        }

        setProcessing(true);
        router.post(
            (action === 'approve' ? approve(order.id) : cancel(order.id)).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setConfirm(null);
                },
            },
        );
    };

    const columns: LineColumn<Line>[] = [
        {
            key: 'product_id',
            header: 'Product',
            className: 'min-w-64 whitespace-normal',
            cell: (line) => (
                <span className="font-medium">{line.product_name}</span>
            ),
        },
        {
            key: 'product_unit_id',
            header: 'Unit',
            cell: (line, _i, set) =>
                editable ? (
                    <NativeSelect
                        aria-label="Unit"
                        value={line.product_unit_id}
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
                ) : (
                    line.units.find((unit) => unit.id === line.product_unit_id)
                        ?.name
                ),
        },
        {
            key: 'quantity',
            header: 'Quantity',
            cell: (line, _i, set) =>
                editable ? (
                    <Input
                        aria-label="Quantity"
                        inputMode="numeric"
                        className="w-24"
                        value={line.quantity}
                        onChange={(event) =>
                            set({ quantity: event.target.value })
                        }
                    />
                ) : (
                    <span>
                        {line.quantity}
                        {line.received !== undefined && line.received > 0 && (
                            <span className="ml-1 text-xs text-muted-foreground">
                                ({line.received} received)
                            </span>
                        )}
                    </span>
                ),
        },
        {
            key: 'unit_cost',
            header: 'Unit cost (₱)',
            cell: (line, _i, set) =>
                editable ? (
                    <Input
                        aria-label="Unit cost"
                        inputMode="decimal"
                        className="w-28"
                        value={line.unit_cost}
                        onChange={(event) =>
                            set({ unit_cost: event.target.value })
                        }
                    />
                ) : (
                    formatMoney(line.unit_cost)
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
    const title = order
        ? `Purchase order ${order.number}`
        : 'New purchase order';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description={
                        order
                            ? [
                                  order.created_by &&
                                      `Created by ${order.created_by}`,
                                  order.approved_by &&
                                      `approved by ${order.approved_by} ${formatDateTime(order.approved_at)}`,
                              ]
                                  .filter(Boolean)
                                  .join(', ')
                            : 'Orders never change stock; goods receipts do.'
                    }
                    actions={
                        order && (
                            <>
                                <DocumentStatusBadge
                                    status={order.status}
                                    label={order.status_label}
                                />
                                {can?.approve && (
                                    <Button
                                        onClick={() => setConfirm('approve')}
                                    >
                                        <CheckCircle2 /> Approve
                                    </Button>
                                )}
                                {can?.receive && (
                                    <Button asChild>
                                        <Link
                                            href={createReceipt({
                                                query: {
                                                    purchase_order: order.id,
                                                },
                                            })}
                                        >
                                            <PackageCheck /> Receive goods
                                        </Link>
                                    </Button>
                                )}
                                {can?.cancel && (
                                    <Button
                                        variant="outline"
                                        onClick={() => setConfirm('cancel')}
                                    >
                                        <XCircle /> Cancel order
                                    </Button>
                                )}
                            </>
                        )
                    }
                />

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
                            disabled={!editable}
                            required
                        />
                        <TextField
                            id="expected_on"
                            label="Expected delivery"
                            type="date"
                            value={form.data.expected_on}
                            onChange={(value) =>
                                form.setData('expected_on', value)
                            }
                            error={errors.expected_on}
                            disabled={!editable}
                        />
                    </div>

                    <LineItemEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        columns={columns}
                        errors={errors}
                        readOnly={!editable}
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
                                      product_id: product.id,
                                      product_name: product.name,
                                      product_unit_id: unit.id,
                                      units,
                                      quantity: 1,
                                      unit_cost: '',
                                  }
                                : null;
                        }}
                        footer={
                            <TableRow>
                                <TableCell colSpan={5}>Order total</TableCell>
                                <TableCell className="text-right">
                                    {formatMoney(total)}
                                </TableCell>
                                {editable && <TableCell />}
                            </TableRow>
                        }
                    />

                    <Field label="Notes" htmlFor="notes" error={errors.notes}>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            disabled={!editable}
                        />
                    </Field>

                    <div className="flex gap-2">
                        {editable && (
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Save draft
                            </Button>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Back</Link>
                        </Button>
                    </div>
                </form>

                <ConfirmDialog
                    open={confirm === 'approve'}
                    onOpenChange={(open) => !open && setConfirm(null)}
                    title="Approve this order?"
                    description="Approved orders can be received but no longer edited."
                    confirmLabel="Approve"
                    processing={processing}
                    onConfirm={() => runAction('approve')}
                />
                <ConfirmDialog
                    open={confirm === 'cancel'}
                    onOpenChange={(open) => !open && setConfirm(null)}
                    title="Cancel this order?"
                    description="The order keeps its number and stays on file as cancelled."
                    confirmLabel="Cancel order"
                    destructive
                    processing={processing}
                    onConfirm={() => runAction('cancel')}
                />
            </div>
        </>
    );
}

OrderForm.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
