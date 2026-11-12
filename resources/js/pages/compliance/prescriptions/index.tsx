import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/compliance/prescriptions';
import type { Column, Paginated, TableState } from '@/types/table';

type Row = {
    id: number;
    number: string;
    patient_name: string;
    prescriber: string;
    prescribed_on: string;
    lines: number;
    open: boolean;
};

const columns: Column<Row>[] = [
    {
        key: 'number',
        header: 'Rx no.',
        sortable: true,
        cell: (row) => row.number,
    },
    {
        key: 'prescribed_on',
        header: 'Date',
        sortable: true,
        cell: (row) => formatDate(row.prescribed_on),
    },
    { key: 'patient', header: 'Patient', cell: (row) => row.patient_name },
    { key: 'prescriber', header: 'Prescriber', cell: (row) => row.prescriber },
    { key: 'lines', header: 'Items', align: 'right', cell: (row) => row.lines },
    {
        key: 'status',
        header: 'Status',
        cell: (row) =>
            row.open ? (
                <StatusBadge tone="warning">Balance left</StatusBadge>
            ) : (
                <StatusBadge tone="success">Fully dispensed</StatusBadge>
            ),
    },
];

export default function PrescriptionsIndex({
    rows,
    state,
}: {
    rows: Paginated<Row>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Prescriptions" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Prescriptions"
                    description="Prescriptions received at the counter, with what is still to be dispensed."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Record prescription
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Rx number or patient"
                    filters={[
                        {
                            name: 'open',
                            label: 'Prescriptions',
                            options: [
                                { value: 'open', label: 'With balance left' },
                                { value: 'closed', label: 'Fully dispensed' },
                            ],
                        },
                    ]}
                    onRowClick={(row) => router.visit(show(row.id))}
                />
            </div>
        </>
    );
}

PrescriptionsIndex.layout = {
    breadcrumbs: [{ title: 'Prescriptions', href: index() }],
};
