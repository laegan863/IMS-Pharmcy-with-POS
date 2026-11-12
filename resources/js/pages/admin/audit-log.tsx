import { Head } from '@inertiajs/react';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/admin/audit-log';
import type { Column, Paginated, TableState } from '@/types/table';

type Changes = {
    attributes?: Record<string, unknown>;
    old?: Record<string, unknown>;
} | null;

type AuditRow = {
    id: number;
    at: string | null;
    log: string | null;
    event: string | null;
    description: string;
    subject: string | null;
    user: string | null;
    branch_id: number | null;
    terminal_id: number | null;
    ip_address: string | null;
    changes: Changes;
    properties: Record<string, unknown> | null;
};

function show(value: unknown): string {
    if (value === null || value === undefined) {
        return '∅';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return JSON.stringify(value);
}

function ChangeList({ changes }: { changes: Changes }) {
    const attributes = changes?.attributes ?? {};
    const old = changes?.old ?? {};
    const keys = Object.keys(attributes);

    if (keys.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <ul className="space-y-0.5 text-xs whitespace-normal">
            {keys.map((key) => (
                <li key={key}>
                    <span className="font-medium">{key}</span>:{' '}
                    {key in old && (
                        <>
                            <span className="text-muted-foreground line-through">
                                {show(old[key])}
                            </span>{' '}
                            →{' '}
                        </>
                    )}
                    {show(attributes[key])}
                </li>
            ))}
        </ul>
    );
}

const columns: Column<AuditRow>[] = [
    {
        key: 'created_at',
        header: 'When',
        sortable: true,
        cell: (row) => formatDateTime(row.at),
    },
    { key: 'user', header: 'User', cell: (row) => row.user ?? 'System' },
    {
        key: 'event',
        header: 'Action',
        cell: (row) => (
            <div className="space-y-1">
                <StatusBadge tone="info">{row.event ?? row.log}</StatusBadge>
                <div className="text-xs text-muted-foreground">
                    {row.subject ?? row.description}
                </div>
            </div>
        ),
    },
    {
        key: 'changes',
        header: 'Values',
        className: 'min-w-64',
        cell: (row) => <ChangeList changes={row.changes} />,
    },
    {
        key: 'where',
        header: 'Where',
        cell: (row) => (
            <div className="text-xs text-muted-foreground">
                {row.branch_id && <div>Branch #{row.branch_id}</div>}
                {row.terminal_id && <div>Terminal #{row.terminal_id}</div>}
                {row.ip_address && <div>{row.ip_address}</div>}
            </div>
        ),
    },
];

export default function AuditLog({
    rows,
    state,
    logs,
    users,
}: {
    rows: Paginated<AuditRow>;
    state: TableState;
    logs: string[];
    users: { value: string; label: string }[];
}) {
    return (
        <>
            <Head title="Audit log" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Audit log"
                    description="Who did what, when, where and with which values. Entries cannot be edited."
                />
                <DataTable
                    rows={rows}
                    state={state}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder="Search action or values"
                    filters={[
                        {
                            name: 'log',
                            label: 'Logs',
                            options: logs.map((log) => ({
                                value: log,
                                label: log,
                            })),
                        },
                        { name: 'user', label: 'Users', options: users },
                    ]}
                />
            </div>
        </>
    );
}

AuditLog.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
