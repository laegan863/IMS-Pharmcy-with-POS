import { Head, router } from '@inertiajs/react';
import { FileDown, FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatNumber,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { excel, index, pdf, show } from '@/routes/reports';

type Column = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'money' | 'date' | 'datetime';
};

type Props = {
    reportKey: string;
    title: string;
    description: string;
    usesDates: boolean;
    filters: { from: string; to: string };
    columns: Column[];
    rows: Record<string, unknown>[];
    totals: Record<string, string>;
    truncated: boolean;
};

function display(value: unknown, type: Column['type']): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const text =
        typeof value === 'string' || typeof value === 'number'
            ? String(value)
            : JSON.stringify(value);

    switch (type) {
        case 'money':
            return formatMoney(text);
        case 'number':
            return formatNumber(text);
        case 'date':
            return formatDate(text);
        case 'datetime':
            return formatDateTime(text);
        default:
            return text;
    }
}

const numeric = (type: Column['type']) => type === 'money' || type === 'number';

/**
 * Any report from App\Reports: date filter, table, totals row and exports.
 */
export default function ReportShow(props: Props) {
    // The report's id is sent as reportKey: React reserves the prop name key.
    const {
        reportKey,
        title,
        description,
        usesDates,
        filters,
        columns,
        rows,
        totals,
        truncated,
    } = props;
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const query = usesDates ? { from, to } : {};

    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get(show(reportKey).url, query, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description={description}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={excel(reportKey, { query }).url}>
                                    <FileSpreadsheet /> Excel
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={pdf(reportKey, { query }).url}>
                                    <FileDown /> PDF
                                </a>
                            </Button>
                        </>
                    }
                />

                {usesDates && (
                    <form
                        onSubmit={apply}
                        className="mb-4 flex flex-wrap items-end gap-3"
                    >
                        <TextField
                            id="from"
                            label="From"
                            type="date"
                            value={from}
                            onChange={setFrom}
                            fieldClassName="w-44"
                        />
                        <TextField
                            id="to"
                            label="To"
                            type="date"
                            value={to}
                            onChange={setTo}
                            fieldClassName="w-44"
                        />
                        <Button type="submit">Show</Button>
                    </form>
                )}

                {truncated && (
                    <Alert className="mb-4">
                        <AlertDescription>
                            Showing the first {rows.length.toLocaleString()}{' '}
                            rows. Export to Excel for the full report.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        className={cn(
                                            numeric(column.type) &&
                                                'text-right',
                                        )}
                                    >
                                        {column.label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length}
                                        className="h-20 text-center text-muted-foreground"
                                    >
                                        No records in this period.
                                    </TableCell>
                                </TableRow>
                            )}
                            {rows.map((row, i) => (
                                <TableRow key={i}>
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn(
                                                numeric(column.type)
                                                    ? 'text-right tabular-nums'
                                                    : 'max-w-xs whitespace-normal',
                                            )}
                                        >
                                            {display(
                                                row[column.key],
                                                column.type,
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                        {Object.keys(totals).length > 0 && rows.length > 0 && (
                            <TableFooter>
                                <TableRow>
                                    {columns.map((column, i) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn(
                                                numeric(column.type) &&
                                                    'text-right tabular-nums',
                                            )}
                                        >
                                            {column.key in totals
                                                ? formatMoney(
                                                      totals[column.key],
                                                  )
                                                : i === 0
                                                  ? 'Total'
                                                  : ''}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableFooter>
                        )}
                    </Table>
                </div>
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
