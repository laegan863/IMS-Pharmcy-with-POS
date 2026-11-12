import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { TextField } from '@/components/form/text-field';
import Heading from '@/components/heading';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, store, update } from '@/routes/admin/branches';
import {
    store as storeTerminal,
    update as updateTerminal,
} from '@/routes/admin/branches/terminals';

type Branch = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
};

type Terminal = {
    id: number;
    code: string;
    name: string;
    machine_identification_number: string | null;
    permit_to_use_number: string | null;
    serial_number: string | null;
    is_active: boolean;
};

function TerminalDialog({
    branchId,
    terminal,
    open,
    onOpenChange,
}: {
    branchId: number;
    terminal: Terminal | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        code: terminal?.code ?? '',
        name: terminal?.name ?? '',
        machine_identification_number:
            terminal?.machine_identification_number ?? '',
        permit_to_use_number: terminal?.permit_to_use_number ?? '',
        serial_number: terminal?.serial_number ?? '',
        is_active: terminal?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => onOpenChange(false) };

        if (terminal) {
            form.put(
                updateTerminal({ branch: branchId, terminal: terminal.id }).url,
                options,
            );
        } else {
            form.post(storeTerminal(branchId).url, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {terminal ? 'Edit terminal' : 'Add terminal'}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="terminal-code"
                            label="Code"
                            value={form.data.code}
                            onChange={(value) => form.setData('code', value)}
                            error={form.errors.code}
                            required
                        />
                        <TextField
                            id="terminal-name"
                            label="Name"
                            value={form.data.name}
                            onChange={(value) => form.setData('name', value)}
                            error={form.errors.name}
                            required
                        />
                    </div>
                    <TextField
                        id="terminal-min"
                        label="BIR machine identification no. (MIN)"
                        value={form.data.machine_identification_number}
                        onChange={(value) =>
                            form.setData('machine_identification_number', value)
                        }
                        error={form.errors.machine_identification_number}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="terminal-ptu"
                            label="Permit to Use no."
                            value={form.data.permit_to_use_number}
                            onChange={(value) =>
                                form.setData('permit_to_use_number', value)
                            }
                            error={form.errors.permit_to_use_number}
                        />
                        <TextField
                            id="terminal-serial"
                            label="Serial no."
                            value={form.data.serial_number}
                            onChange={(value) =>
                                form.setData('serial_number', value)
                            }
                            error={form.errors.serial_number}
                        />
                    </div>
                    <CheckboxField
                        id="terminal-active"
                        label="Active"
                        checked={form.data.is_active}
                        onChange={(value) => form.setData('is_active', value)}
                    />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save terminal
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function BranchForm({
    branch,
    terminals,
}: {
    branch: Branch | null;
    terminals: Terminal[];
}) {
    const form = useForm({
        code: branch?.code ?? '',
        name: branch?.name ?? '',
        address: branch?.address ?? '',
        phone: branch?.phone ?? '',
        is_active: branch?.is_active ?? true,
    });
    const [dialog, setDialog] = useState<{
        open: boolean;
        terminal: Terminal | null;
    }>({ open: false, terminal: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (branch) {
            form.put(update(branch.id).url);
        } else {
            form.post(store().url);
        }
    };

    const title = branch ? branch.name : 'New branch';

    return (
        <>
            <Head title={title} />
            <div className="space-y-10 px-4 py-6">
                <div>
                    <PageHeader title={title} />
                    <form onSubmit={submit} className="max-w-xl space-y-6">
                        <div className="grid gap-6 sm:grid-cols-3">
                            <TextField
                                id="code"
                                label="Code"
                                value={form.data.code}
                                onChange={(value) =>
                                    form.setData('code', value)
                                }
                                error={form.errors.code}
                                required
                            />
                            <TextField
                                id="name"
                                label="Name"
                                value={form.data.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                                error={form.errors.name}
                                fieldClassName="sm:col-span-2"
                                required
                            />
                        </div>
                        <TextField
                            id="address"
                            label="Address"
                            value={form.data.address}
                            onChange={(value) => form.setData('address', value)}
                            error={form.errors.address}
                        />
                        <TextField
                            id="phone"
                            label="Phone"
                            value={form.data.phone}
                            onChange={(value) => form.setData('phone', value)}
                            error={form.errors.phone}
                        />
                        <CheckboxField
                            id="is_active"
                            label="Open for business"
                            checked={form.data.is_active}
                            onChange={(value) =>
                                form.setData('is_active', value)
                            }
                        />
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {branch ? 'Save branch' : 'Create branch'}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Back</Link>
                            </Button>
                        </div>
                    </form>
                </div>

                {branch && (
                    <section className="max-w-4xl space-y-4">
                        <div className="flex items-end justify-between">
                            <Heading
                                variant="small"
                                title="POS terminals"
                                description="Each terminal has its own BIR permit and invoice series."
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    setDialog({ open: true, terminal: null })
                                }
                            >
                                <Plus /> Add terminal
                            </Button>
                        </div>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Code</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>MIN</TableHead>
                                        <TableHead>PTU</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {terminals.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-16 text-center text-muted-foreground"
                                            >
                                                No terminals yet.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {terminals.map((terminal) => (
                                        <TableRow
                                            key={terminal.id}
                                            className="cursor-pointer"
                                            onClick={() =>
                                                setDialog({
                                                    open: true,
                                                    terminal,
                                                })
                                            }
                                        >
                                            <TableCell>
                                                {terminal.code}
                                            </TableCell>
                                            <TableCell>
                                                {terminal.name}
                                            </TableCell>
                                            <TableCell>
                                                {terminal.machine_identification_number ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell>
                                                {terminal.permit_to_use_number ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell>
                                                {terminal.is_active ? (
                                                    <StatusBadge tone="success">
                                                        Active
                                                    </StatusBadge>
                                                ) : (
                                                    <StatusBadge>
                                                        Inactive
                                                    </StatusBadge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {dialog.open && (
                            <TerminalDialog
                                key={dialog.terminal?.id ?? 'new'}
                                branchId={branch.id}
                                terminal={dialog.terminal}
                                open={dialog.open}
                                onOpenChange={(open) =>
                                    setDialog({ ...dialog, open })
                                }
                            />
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

BranchForm.layout = {
    breadcrumbs: [{ title: 'Branches', href: index() }],
};
