import { Head, Link, useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { BatchPicker } from '@/components/batch-picker';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { LineItemEditor } from '@/components/line-item-editor';
import type { LineColumn } from '@/components/line-item-editor';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatMoney } from '@/lib/format';
import { batches, index, store, update } from '@/routes/purchasing/returns';

type Line = {
    product_id: number;
    product_name: string;
    batch_id: number | null;
    lot_number?: string;
    expires_on?: string;
    quantity_base: number | string;
    unit_cost: string;
};

type SupplierReturn = {
    id: number;
    number: string | null;
    status: string;
    supplier_id: number;
    reason: string;
    total: string;
    lines: Line[];
};

export default function ReturnForm({
    supplierReturn,
    suppliers,
}: {
    supplierReturn: SupplierReturn | null;
    suppliers: Option[];
}) {
    const posted = supplierReturn?.status === 'posted';
    const [confirmPost, setConfirmPost] = useState(false);
    const form = useForm({
        supplier_id: supplierReturn ? String(supplierReturn.supplier_id) : '',
        reason: supplierReturn?.reason ?? '',
        lines: (supplierReturn?.lines ?? []) as Line[],
        post: false,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const send = (post: boolean) => {
        form.transform((data) => ({ ...data, post }));
        const options = {
            preserveScroll: true,
            onFinish: () => setConfirmPost(false),
        };

        if (supplierReturn) {
            form.put(update(supplierReturn.id).url, options);
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
                <span className="font-medium">{line.product_name}</span>
            ),
        },
        {
            key: 'batch_id',
            header: 'Batch',
            cell: (line, _i, set) =>
                posted ? (
                    `${line.lot_number} · exp ${formatDate(line.expires_on)}`
                ) : (
                    <BatchPicker
                        url={
                            batches({ query: { product_id: line.product_id } })
                                .url
                        }
                        value={line.batch_id}
                        onChange={(batch) =>
                            set({
                                batch_id: batch.batch_id,
                                unit_cost: batch.unit_cost,
                            })
                        }
                    />
                ),
        },
        {
            key: 'quantity_base',
            header: 'Quantity (base units)',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Quantity"
                    inputMode="numeric"
                    className="w-24"
                    value={line.quantity_base}
                    disabled={posted}
                    onChange={(event) =>
                        set({ quantity_base: event.target.value })
                    }
                />
            ),
        },
        {
            key: 'value',
            header: 'Value at cost',
            className: 'text-right',
            cell: (line) =>
                formatMoney(
                    (Number(line.quantity_base) || 0) *
                        (Number(line.unit_cost) || 0),
                ),
        },
    ];

    const title = supplierReturn?.number
        ? `Supplier return ${supplierReturn.number}`
        : 'Return to supplier';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description="Stock leaves the named batches when the return is posted."
                    actions={
                        supplierReturn && (
                            <DocumentStatusBadge
                                status={supplierReturn.status}
                            />
                        )
                    }
                />
                <form onSubmit={submit} className="max-w-5xl space-y-6">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <SelectField
                            id="supplier_id"
                            label="Supplier"
                            value={form.data.supplier_id}
                            options={suppliers}
                            onChange={(value) =>
                                form.setData('supplier_id', value)
                            }
                            error={errors.supplier_id}
                            disabled={posted}
                            required
                        />
                        <TextField
                            id="reason"
                            label="Reason"
                            value={form.data.reason}
                            onChange={(value) => form.setData('reason', value)}
                            error={errors.reason}
                            disabled={posted}
                            placeholder="Damaged, near expiry, recalled…"
                            required
                        />
                    </div>
                    <LineItemEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        columns={columns}
                        errors={errors}
                        readOnly={posted}
                        makeLine={(product) => ({
                            product_id: product.id,
                            product_name: product.name,
                            batch_id: null,
                            quantity_base: 1,
                            unit_cost: '0',
                        })}
                    />
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
                                    <Send /> Post return
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
                    title="Post this return?"
                    description="The stock is taken out of the batches now. This cannot be undone."
                    confirmLabel="Post return"
                    processing={form.processing}
                    onConfirm={() => send(true)}
                />
            </div>
        </>
    );
}

ReturnForm.layout = {
    breadcrumbs: [{ title: 'Supplier returns', href: index() }],
};
