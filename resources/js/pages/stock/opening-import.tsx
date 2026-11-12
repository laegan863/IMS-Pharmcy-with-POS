import { Head, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import type { FormEvent } from 'react';
import { Field } from '@/components/form/field';
import { ImportReport } from '@/components/import-report';
import type { ImportError } from '@/components/import-report';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber } from '@/lib/format';
import { index, opening } from '@/routes/stock';
import { store, template } from '@/routes/stock/opening';

type Report = { errors: ImportError[]; batches: number; units: number };

export default function OpeningImport({
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
            <Head title="Opening stock" />
            <div className="max-w-4xl space-y-8 px-4 py-6">
                <PageHeader
                    title="Opening stock"
                    description="Load the shelf count for this branch: one row per batch. Use it once, at go-live, after a full count."
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

                {report && (
                    <ImportReport
                        errors={report.errors}
                        success={`${report.batches} batches loaded, ${formatNumber(report.units)} base units in total.`}
                    />
                )}

                <section className="space-y-2 text-sm text-muted-foreground">
                    <h2 className="font-medium text-foreground">Columns</h2>
                    <p className="font-mono text-xs">{columns.join(', ')}</p>
                    <p>
                        unit may be blank for the base unit (tablet, piece).
                        unit_cost is the cost of one of that unit; it is divided
                        down to a cost per base unit.
                    </p>
                </section>
            </div>
        </>
    );
}

OpeningImport.layout = {
    breadcrumbs: [
        { title: 'Stock on hand', href: index() },
        { title: 'Opening stock', href: opening() },
    ],
};
