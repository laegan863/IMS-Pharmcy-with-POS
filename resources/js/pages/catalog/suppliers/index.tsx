import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { create, edit, index } from '@/routes/catalog/suppliers';
import type { Column, Paginated, TableState } from '@/types/table';

type SupplierRow = {
    id: number;
    code: string;
    name: string;
    tin: string | null;
    phone: string | null;
    payment_terms_days: number;
    fda_license_expires_on: string | null;
    fda_license_expired: boolean;
    is_active: boolean;
};

const columns: Column<SupplierRow>[] = [
    { key: 'code', header: 'Code', sortable: true, cell: (row) => row.code },
    {
        key: 'name',
        header: 'Name',
        sortable: true,
        cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    { key: 'tin', header: 'TIN', cell: (row) => row.tin ?? '—' },
    {
        key: 'terms',
        header: 'Terms',
        cell: (row) =>
            row.payment_terms_days === 0
                ? 'COD'
                : `${row.payment_terms_days} days`,
    },
    {
        key: 'fda_license_expires_on',
        header: 'FDA licence',
        sortable: true,
        cell: (row) =>
            row.fda_license_expired ? (
                <StatusBadge tone="danger">
                    Expired {formatDate(row.fda_license_expires_on)}
                </StatusBadge>
            ) : (
                formatDate(row.fda_license_expires_on)
            ),
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) =>
            row.is_active ? (
                <StatusBadge tone="success">Active</StatusBadge>
            ) : (
                <StatusBadge>Inactive</StatusBadge>
            ),
    },
];

export default function SuppliersIndex({
    rows,
    state,
}: {
    rows: Paginated<SupplierRow>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Suppliers" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Suppliers"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New supplier
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Search name, code or TIN"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statuses',
                            options: [
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ],
                        },
                    ]}
                    onRowClick={(row) => router.visit(edit(row.id))}
                />
            </div>
        </>
    );
}

SuppliersIndex.layout = {
    breadcrumbs: [{ title: 'Suppliers', href: index() }],
};
