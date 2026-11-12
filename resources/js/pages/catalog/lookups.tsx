import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DataTable } from '@/components/data-table';
import { CheckboxField } from '@/components/form/checkbox-field';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/catalog/lookups';
import type { Column, Paginated, TableState } from '@/types/table';

type LookupRow = { id: number; name: string; is_active: boolean };

type Props = {
    rows: Paginated<LookupRow>;
    state: TableState;
    type: string;
    types: { value: string; label: string }[];
};

function LookupDialog({
    type,
    row,
    onClose,
}: {
    type: string;
    row: LookupRow | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: row?.name ?? '',
        is_active: row?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (row) {
            form.put(update({ type, id: row.id }).url, options);
        } else {
            form.post(store(type).url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{row ? 'Edit' : 'Add'}</DialogTitle>
                    </DialogHeader>
                    <TextField
                        id="lookup-name"
                        label="Name"
                        value={form.data.name}
                        onChange={(value) => form.setData('name', value)}
                        error={form.errors.name}
                        autoFocus
                        required
                    />
                    <CheckboxField
                        id="lookup-active"
                        label="Active"
                        checked={form.data.is_active}
                        onChange={(value) => form.setData('is_active', value)}
                    />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Lookups({ rows, state, type, types }: Props) {
    const [editing, setEditing] = useState<LookupRow | null | undefined>(
        undefined,
    );
    const label = types.find((item) => item.value === type)?.label ?? '';

    const columns: Column<LookupRow>[] = [
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            cell: (row) => row.name,
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

    return (
        <>
            <Head title={label} />
            <div className="px-4 py-6">
                <PageHeader
                    title="Lookups"
                    description="Pick-lists used on the product form"
                    actions={
                        <Button onClick={() => setEditing(null)}>
                            <Plus /> Add
                        </Button>
                    }
                />
                <nav className="mb-4 flex flex-wrap gap-1" aria-label="Lists">
                    {types.map((item) => (
                        <Button
                            key={item.value}
                            variant="ghost"
                            size="sm"
                            asChild
                            className={cn(item.value === type && 'bg-muted')}
                        >
                            <Link href={index(item.value)}>{item.label}</Link>
                        </Button>
                    ))}
                </nav>
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    onRowClick={(row) => setEditing(row)}
                />
                {editing !== undefined && (
                    <LookupDialog
                        key={editing?.id ?? 'new'}
                        type={type}
                        row={editing}
                        onClose={() => setEditing(undefined)}
                    />
                )}
            </div>
        </>
    );
}

Lookups.layout = {
    breadcrumbs: [{ title: 'Lookups', href: index('generic-names') }],
};
