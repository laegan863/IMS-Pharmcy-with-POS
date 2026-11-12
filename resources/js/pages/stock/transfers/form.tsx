import { Head, Link, router, useForm } from '@inertiajs/react';
import { PackageCheck, Truck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { BatchPicker } from '@/components/batch-picker';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { Field } from '@/components/form/field';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { LineItemEditor } from '@/components/line-item-editor';
import type { LineColumn } from '@/components/line-item-editor';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/format';
import { lookup } from '@/routes/stock/batches';
import { index, receive, store, update } from '@/routes/stock/transfers';

type Line = {
    product_id: number;
    product_name: string;
    batch_id: number | null;
    lot_number?: string;
    expires_on?: string;
    quantity_base: number | string;
};

type Transfer = {
    id: number;
    number: string | null;
    status: string;
    from: string;
    to_branch_id: number;
    to: string;
    note: string | null;
    is_sender: boolean;
    is_receiver: boolean;
    dispatched_at: string | null;
    received_at: string | null;
    lines: Line[];
};

export default function TransferForm({
    transfer,
    branches,
}: {
    transfer: Transfer | null;
    branches: Option[];
}) {
    const editable =
        !transfer || (transfer.status === 'draft' && transfer.is_sender);
    const [confirm, setConfirm] = useState<'dispatch' | 'receive' | null>(null);
    const form = useForm({
        to_branch_id: transfer ? String(transfer.to_branch_id) : '',
        note: transfer?.note ?? '',
        lines: (transfer?.lines ?? []) as Line[],
        dispatch: false,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const send = (dispatch: boolean) => {
        form.transform((data) => ({ ...data, dispatch }));
        const options = {
            preserveScroll: true,
            onFinish: () => setConfirm(null),
        };

        if (transfer) {
            form.put(update(transfer.id).url, options);
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
            cell: (line) => line.product_name,
        },
        {
            key: 'batch_id',
            header: 'Batch',
            cell: (line, _i, set) =>
                editable ? (
                    <BatchPicker
                        url={
                            lookup({ query: { product_id: line.product_id } })
                                .url
                        }
                        value={line.batch_id}
                        onChange={(batch) => set({ batch_id: batch.batch_id })}
                    />
                ) : (
                    `${line.lot_number} · exp ${formatDate(line.expires_on)}`
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
                    disabled={!editable}
                    onChange={(event) =>
                        set({ quantity_base: event.target.value })
                    }
                />
            ),
        },
    ];

    const title = transfer?.number
        ? `Transfer ${transfer.number}`
        : transfer
          ? 'Transfer (draft)'
          : 'New transfer';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description={
                        transfer
                            ? `${transfer.from} → ${transfer.to}${transfer.dispatched_at ? ` · dispatched ${formatDateTime(transfer.dispatched_at)}` : ''}${transfer.received_at ? ` · received ${formatDateTime(transfer.received_at)}` : ''}`
                            : 'Send stock from this branch to another.'
                    }
                    actions={
                        transfer && (
                            <>
                                <DocumentStatusBadge status={transfer.status} />
                                {transfer.status === 'in_transit' &&
                                    transfer.is_receiver && (
                                        <Button
                                            onClick={() =>
                                                setConfirm('receive')
                                            }
                                        >
                                            <PackageCheck /> Receive
                                        </Button>
                                    )}
                            </>
                        )
                    }
                />
                <form onSubmit={submit} className="max-w-5xl space-y-6">
                    <SelectField
                        id="to_branch_id"
                        label="Send to"
                        value={form.data.to_branch_id}
                        options={branches}
                        onChange={(value) =>
                            form.setData('to_branch_id', value)
                        }
                        error={errors.to_branch_id}
                        disabled={!editable}
                        className="max-w-sm"
                        required
                    />
                    <LineItemEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        columns={columns}
                        errors={errors}
                        readOnly={!editable}
                        makeLine={(product) => ({
                            product_id: product.id,
                            product_name: product.name,
                            batch_id: null,
                            quantity_base: '',
                        })}
                    />
                    <Field label="Note" htmlFor="note">
                        <Textarea
                            id="note"
                            value={form.data.note}
                            disabled={!editable}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                    </Field>
                    <div className="flex gap-2">
                        {editable && (
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
                                    onClick={() => setConfirm('dispatch')}
                                >
                                    <Truck /> Dispatch
                                </Button>
                            </>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Back</Link>
                        </Button>
                    </div>
                </form>
                <ConfirmDialog
                    open={confirm === 'dispatch'}
                    onOpenChange={(open) => !open && setConfirm(null)}
                    title="Dispatch this transfer?"
                    description="The stock leaves this branch now and is in transit until the other branch receives it."
                    confirmLabel="Dispatch"
                    processing={form.processing}
                    onConfirm={() => send(true)}
                />
                <ConfirmDialog
                    open={confirm === 'receive'}
                    onOpenChange={(open) => !open && setConfirm(null)}
                    title="Receive this transfer?"
                    description="Check the goods against the list first. The stock is added to this branch."
                    confirmLabel="Receive"
                    onConfirm={() =>
                        transfer &&
                        router.post(
                            receive(transfer.id).url,
                            {},
                            { onFinish: () => setConfirm(null) },
                        )
                    }
                />
            </div>
        </>
    );
}

TransferForm.layout = {
    breadcrumbs: [{ title: 'Branch transfers', href: index() }],
};
