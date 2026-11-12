import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatMoney } from '@/lib/format';
import { index, show, store } from '@/routes/pos/readings';
import type { Column, Paginated, TableState } from '@/types/table';

type ReadingRow = {
    id: number;
    number: string;
    z_counter: number;
    terminal: string;
    period_start: string;
    period_end: string;
    net_sales: string;
    grand_total_after: string;
    user: string;
};

const columns: Column<ReadingRow>[] = [
    {
        key: 'number',
        header: 'Report',
        cell: (row) => (
            <a
                className="underline"
                href={show(row.id).url}
                target="_blank"
                rel="noreferrer"
            >
                {row.number}
            </a>
        ),
    },
    { key: 'z', header: 'Z no.', cell: (row) => row.z_counter },
    { key: 'terminal', header: 'Terminal', cell: (row) => row.terminal },
    {
        key: 'created_at',
        header: 'Period',
        sortable: true,
        cell: (row) =>
            `${formatDateTime(row.period_start)} – ${formatDateTime(row.period_end)}`,
    },
    {
        key: 'net',
        header: 'Net sales',
        align: 'right',
        cell: (row) => formatMoney(row.net_sales),
    },
    {
        key: 'gt',
        header: 'Grand total',
        align: 'right',
        cell: (row) => formatMoney(row.grand_total_after),
    },
    { key: 'user', header: 'By', cell: (row) => row.user },
];

export default function Readings({
    rows,
    state,
    terminals,
}: {
    rows: Paginated<ReadingRow>;
    state: TableState;
    terminals: Option[];
}) {
    const [confirm, setConfirm] = useState(false);
    const form = useForm({ terminal_id: terminals[0]?.value ?? '' });

    return (
        <>
            <Head title="Z-readings" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Z-readings"
                    description="End-of-day totals per terminal. Close every shift on the terminal first. The grand total is never reset."
                />
                <div className="mb-6 flex items-end gap-3">
                    <SelectField
                        id="terminal_id"
                        label="Terminal"
                        value={form.data.terminal_id}
                        options={terminals}
                        onChange={(value) => form.setData('terminal_id', value)}
                        error={
                            form.errors.terminal_id ??
                            (form.errors as Record<string, string>).terminal
                        }
                        className="w-72"
                    />
                    <Button
                        onClick={() => setConfirm(true)}
                        disabled={!form.data.terminal_id}
                    >
                        Run Z-reading
                    </Button>
                </div>
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                />
            </div>
            <ConfirmDialog
                open={confirm}
                onOpenChange={setConfirm}
                title="Run the Z-reading now?"
                description="It closes the day for this terminal. Sales in it can then only be refunded, not voided."
                confirmLabel="Run Z-reading"
                processing={form.processing}
                onConfirm={() =>
                    form.post(store().url, {
                        onFinish: () => setConfirm(false),
                    })
                }
            />
        </>
    );
}

Readings.layout = {
    breadcrumbs: [{ title: 'Z-readings', href: index() }],
};
