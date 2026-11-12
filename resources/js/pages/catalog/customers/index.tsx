import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/catalog/customers';
import type { Column, Paginated, TableState } from '@/types/table';

type CustomerRow = {
    id: number;
    name: string;
    phone: string | null;
    senior_citizen_id_masked: string | null;
    pwd_id_masked: string | null;
    is_active: boolean;
};

const columns: Column<CustomerRow>[] = [
    {
        key: 'name',
        header: 'Name',
        sortable: true,
        cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    { key: 'phone', header: 'Phone', cell: (row) => row.phone ?? '—' },
    {
        key: 'ids',
        header: 'Discount IDs',
        cell: (row) => (
            <div className="flex gap-1">
                {row.senior_citizen_id_masked && (
                    <StatusBadge tone="info">
                        Senior {row.senior_citizen_id_masked}
                    </StatusBadge>
                )}
                {row.pwd_id_masked && (
                    <StatusBadge tone="info">
                        PWD {row.pwd_id_masked}
                    </StatusBadge>
                )}
            </div>
        ),
    },
];

export default function CustomersIndex({
    rows,
    state,
}: {
    rows: Paginated<CustomerRow>;
    state: TableState;
}) {
    return (
        <>
            <Head title="Customers" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Customers"
                    description="ID numbers are encrypted and shown masked."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New customer
                            </Link>
                        </Button>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Name, phone or full ID number"
                    filters={[
                        {
                            name: 'type',
                            label: 'Types',
                            options: [
                                { value: 'senior', label: 'Senior citizens' },
                                { value: 'pwd', label: 'PWD' },
                            ],
                        },
                    ]}
                    onRowClick={(row) => router.visit(edit(row.id))}
                />
            </div>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Customers', href: index() }],
};
