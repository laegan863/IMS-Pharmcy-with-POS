import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index, show, store } from '@/routes/stock/counts';
import type { Column, Paginated, TableState } from '@/types/table';

type Row = {
    id: number;
    number: string;
    status: string;
    scope: string;
    lines: number;
    created_by: string;
    created_at: string;
};

const columns: Column<Row>[] = [
    { key: 'number', header: 'Number', cell: (row) => row.number },
    {
        key: 'created_at',
        header: 'Started',
        sortable: true,
        cell: (row) => formatDateTime(row.created_at),
    },
    { key: 'scope', header: 'Scope', cell: (row) => row.scope },
    {
        key: 'lines',
        header: 'Batches',
        align: 'right',
        cell: (row) => row.lines,
    },
    { key: 'created_by', header: 'By', cell: (row) => row.created_by },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => <DocumentStatusBadge status={row.status} />,
    },
];

export default function CountsIndex({
    rows,
    state,
    categories,
}: {
    rows: Paginated<Row>;
    state: TableState;
    categories: Option[];
}) {
    const form = useForm({ category_id: '' });

    return (
        <>
            <Head title="Stock counts" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Stock counts"
                    description="Starting a count freezes the system quantities. Count, review the variances, then a pharmacist or the owner posts."
                />
                <div className="mb-6 flex flex-wrap items-end gap-3">
                    <SelectField
                        id="category_id"
                        label="Count one category (or leave blank for all stock)"
                        value={form.data.category_id}
                        options={categories}
                        onChange={(value) => form.setData('category_id', value)}
                        error={(form.errors as Record<string, string>).scope}
                        className="w-80"
                    />
                    <Button
                        disabled={form.processing}
                        onClick={() => form.post(store().url)}
                    >
                        <ClipboardCheck /> Start count
                    </Button>
                </div>
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    onRowClick={(row) => router.visit(show(row.id))}
                />
            </div>
        </>
    );
}

CountsIndex.layout = {
    breadcrumbs: [{ title: 'Stock counts', href: index() }],
};
