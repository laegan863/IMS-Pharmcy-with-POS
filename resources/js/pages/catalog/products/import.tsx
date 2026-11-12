import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, FileWarning } from 'lucide-react';
import type { FormEvent } from 'react';
import { Field } from '@/components/form/field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/catalog/products';
import { importMethod } from '@/routes/catalog/products';
import { store, template } from '@/routes/catalog/products/import';

type Report = {
    errors: { row: number; column: string; message: string }[];
    created: number;
    updated: number;
};

export default function ProductImport({
    columns,
    report,
}: {
    columns: string[];
    report: Report | null;
}) {
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, { forceFormData: true });
    };

    return (
        <>
            <Head title="Import products" />
            <div className="max-w-4xl space-y-8 px-4 py-6">
                <PageHeader
                    title="Import products"
                    description="Upload the store's product list. Every row is checked first; nothing is saved unless the whole file is valid. Existing SKUs are updated."
                    actions={
                        <Button variant="outline" asChild>
                            <a href={template().url}>
                                <Download /> Download template
                            </a>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="flex items-end gap-3">
                    <Field
                        label="Excel or CSV file"
                        htmlFor="file"
                        error={form.errors.file}
                        className="flex-1"
                    >
                        <Input
                            id="file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(event) =>
                                form.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                            required
                        />
                    </Field>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.data.file}
                    >
                        {form.processing && <Spinner />}
                        Check and import
                    </Button>
                </form>

                {report && report.errors.length === 0 && (
                    <Alert>
                        <CheckCircle2 />
                        <AlertTitle>Import finished</AlertTitle>
                        <AlertDescription>
                            {report.created} products created, {report.updated}{' '}
                            updated.
                        </AlertDescription>
                    </Alert>
                )}

                {report && report.errors.length > 0 && (
                    <div className="space-y-3">
                        <Alert variant="destructive">
                            <FileWarning />
                            <AlertTitle>
                                Nothing was imported: {report.errors.length}{' '}
                                problem{report.errors.length === 1 ? '' : 's'}{' '}
                                found
                            </AlertTitle>
                            <AlertDescription>
                                Fix these rows in the file and upload it again.
                            </AlertDescription>
                        </Alert>
                        <div className="max-h-[28rem] overflow-y-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-20">
                                            Row
                                        </TableHead>
                                        <TableHead className="w-48">
                                            Column
                                        </TableHead>
                                        <TableHead>Problem</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.errors.map((error, i) => (
                                        <TableRow key={i}>
                                            <TableCell>{error.row}</TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {error.column}
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                {error.message}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                )}

                <section className="space-y-2 text-sm text-muted-foreground">
                    <h2 className="font-medium text-foreground">
                        Columns, in this order
                    </h2>
                    <p className="font-mono text-xs leading-relaxed">
                        {columns.join(', ')}
                    </p>
                    <p>
                        classification: rx, otc, dangerous_drug or non_drug.
                        tax_class: vatable, vat_exempt or zero_rated. The base
                        unit is what stock is counted in; unit2 and unit3 are
                        larger units with how many base units they hold.
                    </p>
                </section>
            </div>
        </>
    );
}

ProductImport.layout = {
    breadcrumbs: [
        { title: 'Products', href: index() },
        { title: 'Import', href: importMethod() },
    ],
};
