import { Head, Link, router } from '@inertiajs/react';
import { Plus, Upload } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Option } from '@/components/form/select-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { create, edit, importMethod, index } from '@/routes/catalog/products';
import type { Column, Paginated, TableState } from '@/types/table';

type ProductRow = {
    id: number;
    sku: string;
    name: string;
    generic_name: string;
    brand_name: string | null;
    category: string | null;
    classification: string;
    classification_label: string;
    units: { name: string; conversion_factor: number; price: string | null }[];
    is_active: boolean;
};

type Props = {
    rows: Paginated<ProductRow>;
    state: TableState;
    classifications: Option[];
    categories: Option[];
    canManage: boolean;
    canImport: boolean;
};

const classificationTone = {
    rx: 'warning',
    dangerous_drug: 'danger',
    otc: 'info',
    non_drug: 'neutral',
} as const;

const columns: Column<ProductRow>[] = [
    { key: 'sku', header: 'SKU', sortable: true, cell: (row) => row.sku },
    {
        key: 'generic_name',
        header: 'Product',
        sortable: true,
        cell: (row) => (
            <div className="max-w-md whitespace-normal">
                <div className="font-medium">{row.name}</div>
                {row.category && (
                    <div className="text-xs text-muted-foreground">
                        {row.category}
                    </div>
                )}
            </div>
        ),
    },
    {
        key: 'classification',
        header: 'Class',
        cell: (row) => (
            <StatusBadge
                tone={
                    classificationTone[
                        row.classification as keyof typeof classificationTone
                    ] ?? 'neutral'
                }
            >
                {row.classification_label}
            </StatusBadge>
        ),
    },
    {
        key: 'prices',
        header: 'Units and prices',
        cell: (row) => (
            <div className="space-y-0.5 text-xs">
                {row.units.map((unit) => (
                    <div key={unit.name}>
                        {unit.name}
                        {unit.conversion_factor > 1 &&
                            ` (${unit.conversion_factor})`}
                        : {formatMoney(unit.price)}
                    </div>
                ))}
            </div>
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

export default function ProductsIndex({
    rows,
    state,
    classifications,
    categories,
    canManage,
    canImport,
}: Props) {
    return (
        <>
            <Head title="Products" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Products"
                    description="The catalog shared by every branch"
                    actions={
                        <>
                            {canImport && (
                                <Button variant="outline" asChild>
                                    <Link href={importMethod()}>
                                        <Upload /> Import
                                    </Link>
                                </Button>
                            )}
                            {canManage && (
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus /> New product
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Brand, generic name, SKU or barcode"
                    filters={[
                        {
                            name: 'classification',
                            label: 'Classes',
                            options: classifications,
                        },
                        {
                            name: 'category',
                            label: 'Categories',
                            options: categories,
                        },
                        {
                            name: 'status',
                            label: 'Statuses',
                            options: [
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ],
                        },
                    ]}
                    onRowClick={
                        canManage
                            ? (row) => router.visit(edit(row.id))
                            : undefined
                    }
                />
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: index() }],
};
