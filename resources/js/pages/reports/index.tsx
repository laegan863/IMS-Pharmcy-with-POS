import { Head, Link } from '@inertiajs/react';
import { FileBarChart } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { index, show } from '@/routes/reports';

type ReportSummary = {
    key: string;
    title: string;
    description: string;
    group: string;
};

export default function ReportsIndex({
    reports,
}: {
    reports: ReportSummary[];
}) {
    const groups = [...new Set(reports.map((report) => report.group))];

    return (
        <>
            <Head title="Reports" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Reports"
                    description="Every report can be filtered by date and exported to Excel or PDF."
                />
                <div className="space-y-8">
                    {groups.map((group) => (
                        <section key={group}>
                            <h2 className="mb-3 text-sm font-medium text-muted-foreground">
                                {group}
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {reports
                                    .filter((report) => report.group === group)
                                    .map((report) => (
                                        <Link
                                            key={report.key}
                                            href={show(report.key)}
                                            className="flex gap-3 rounded-lg border p-4 transition-colors hover:bg-muted/50"
                                        >
                                            <FileBarChart className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                            <div>
                                                <div className="font-medium">
                                                    {report.title}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {report.description}
                                                </p>
                                            </div>
                                        </Link>
                                    ))}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
