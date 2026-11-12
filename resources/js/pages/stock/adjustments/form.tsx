import { Head, Link, useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { BatchPicker } from '@/components/batch-picker';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { Field } from '@/components/form/field';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { LineItemEditor } from '@/components/line-item-editor';
import type { LineColumn } from '@/components/line-item-editor';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatMoney } from '@/lib/format';
import { index, store, update } from '@/routes/stock/adjustments';
import { lookup } from '@/routes/stock/batches';

type Line = {
    product_id: number;
    product_name: string;
    batch_id: number | null;
    lot_number?: string;
    expires_on?: string;
    quantity_change: number | string;
    unit_cost: string;
};

type Adjustment = {
    id: number;
    number: string | null;
    status: string;
    reason_code: string;
    note: string | null;
    disposal_method: string | null;
    witness_name: string | null;
    value: string;
    created_by: string;
    approved_by: string | null;
    posted_at: string | null;
    lines: Line[];
};

type Props = {
    type: 'adjustment' | 'disposal';
    adjustment: Adjustment | null;
    reasons: Option[];
    canPost: boolean;
};

export default function AdjustmentForm({
    type,
    adjustment,
    reasons,
    canPost,
}: Props) {
    const posted = adjustment?.status === 'posted';
    const disposal = type === 'disposal';
    const [confirm, setConfirm] = useState(false);
    const form = useForm({
        reason_code: adjustment?.reason_code ?? (disposal ? 'expired' : ''),
        note: adjustment?.note ?? '',
        disposal_method: adjustment?.disposal_method ?? '',
        witness_name: adjustment?.witness_name ?? '',
        lines: (adjustment?.lines ?? []) as Line[],
        post: false,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const send = (post: boolean) => {
        form.transform((data) => ({ ...data, post }));
        const options = {
            preserveScroll: true,
            onFinish: () => setConfirm(false),
        };

        if (adjustment) {
            form.put(update(adjustment.id).url, options);
        } else {
            form.post(store(type).url, options);
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
            cell: (line) => line.product_name,
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
                            lookup({ query: { product_id: line.product_id } })
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
            key: 'quantity_change',
            header: disposal
                ? 'Quantity to dispose'
                : 'Change (+ add / − remove)',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Quantity"
                    inputMode="numeric"
                    className="w-28"
                    value={line.quantity_change}
                    disabled={posted}
                    onChange={(event) =>
                        set({ quantity_change: event.target.value })
                    }
                />
            ),
        },
        {
            key: 'value',
            header: 'Value',
            className: 'text-right',
            cell: (line) =>
                formatMoney(
                    (Number(line.quantity_change) || 0) *
                        (Number(line.unit_cost) || 0) *
                        (disposal ? -1 : 1),
                ),
        },
    ];

    const noun = disposal ? 'Disposal' : 'Stock adjustment';
    const title = adjustment?.number
        ? `${noun} ${adjustment.number}`
        : adjustment
          ? `${noun} (draft)`
          : `New ${noun.toLowerCase()}`;

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description={
                        adjustment
                            ? `Drafted by ${adjustment.created_by}${adjustment.approved_by ? `, posted by ${adjustment.approved_by}` : ''}.`
                            : 'Quantities are in base units.'
                    }
                    actions={
                        adjustment && (
                            <DocumentStatusBadge status={adjustment.status} />
                        )
                    }
                />
                <form onSubmit={submit} className="max-w-5xl space-y-6">
                    <div className="grid gap-6 sm:grid-cols-3">
                        <SelectField
                            id="reason_code"
                            label="Reason"
                            value={form.data.reason_code}
                            options={reasons}
                            onChange={(value) =>
                                form.setData('reason_code', value)
                            }
                            error={errors.reason_code}
                            disabled={posted}
                            required
                        />
                        {disposal && (
                            <>
                                <TextField
                                    id="disposal_method"
                                    label="Disposal method"
                                    value={form.data.disposal_method}
                                    onChange={(value) =>
                                        form.setData('disposal_method', value)
                                    }
                                    error={errors.disposal_method}
                                    disabled={posted}
                                    required
                                />
                                <TextField
                                    id="witness_name"
                                    label="Witness"
                                    value={form.data.witness_name}
                                    onChange={(value) =>
                                        form.setData('witness_name', value)
                                    }
                                    error={errors.witness_name}
                                    disabled={posted}
                                    required
                                />
                            </>
                        )}
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
                            quantity_change: disposal ? '' : '',
                            unit_cost: '0',
                        })}
                    />
                    <Field label="Note" htmlFor="note" error={errors.note}>
                        <Textarea
                            id="note"
                            value={form.data.note}
                            disabled={posted}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                    </Field>
                    <div className="flex gap-2">
                        {!posted && (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Save draft
                            </Button>
                        )}
                        {!posted && canPost && (
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={() => setConfirm(true)}
                            >
                                <Send /> Approve and post
                            </Button>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={index(type)}>Back</Link>
                        </Button>
                    </div>
                </form>
                <ConfirmDialog
                    open={confirm}
                    onOpenChange={setConfirm}
                    title={`Post this ${disposal ? 'disposal' : 'adjustment'}?`}
                    description="Stock changes now. This cannot be undone; a correction is a new adjustment."
                    confirmLabel="Post"
                    processing={form.processing}
                    onConfirm={() => send(true)}
                />
            </div>
        </>
    );
}

AdjustmentForm.layout = {
    breadcrumbs: [{ title: 'Stock adjustments', href: index('adjustment') }],
};
