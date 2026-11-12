import { Head } from '@inertiajs/react';
import { Image } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { index, photo } from '@/routes/compliance/prescriptions';

type Prescription = {
    id: number;
    number: string;
    patient_name: string;
    patient_address: string | null;
    prescriber: string;
    prc: string | null;
    s2: string | null;
    prescribed_on: string;
    special_form_serial: string | null;
    notes: string | null;
    has_photo: boolean;
    created_by: string;
    lines: {
        product: string;
        prescribed: number;
        dispensed: number;
        balance: number;
        directions: string | null;
    }[];
};

export default function PrescriptionShow({
    prescription,
}: {
    prescription: Prescription;
}) {
    return (
        <>
            <Head title={`Prescription ${prescription.number}`} />
            <div className="max-w-4xl px-4 py-6">
                <PageHeader
                    title={`Prescription ${prescription.number}`}
                    description={`${prescription.patient_name} · ${prescription.prescriber} (PRC ${prescription.prc ?? '—'}${prescription.s2 ? `, S2 ${prescription.s2}` : ''}) · ${formatDate(prescription.prescribed_on)}`}
                    actions={
                        prescription.has_photo && (
                            <Button variant="outline" asChild>
                                <a
                                    href={photo(prescription.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Image /> Photo
                                </a>
                            </Button>
                        )
                    }
                />
                {prescription.special_form_serial && (
                    <p className="mb-4 text-sm">
                        Special prescription form:{' '}
                        {prescription.special_form_serial}
                    </p>
                )}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Medicine</TableHead>
                                <TableHead>Directions</TableHead>
                                <TableHead className="text-right">
                                    Prescribed
                                </TableHead>
                                <TableHead className="text-right">
                                    Dispensed
                                </TableHead>
                                <TableHead className="text-right">
                                    Balance
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {prescription.lines.map((line, i) => (
                                <TableRow key={i}>
                                    <TableCell className="whitespace-normal">
                                        {line.product}
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        {line.directions ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {line.prescribed}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {line.dispensed}
                                    </TableCell>
                                    <TableCell className="text-right font-medium">
                                        {line.balance}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {prescription.notes && (
                    <p className="mt-4 text-sm text-muted-foreground">
                        {prescription.notes}
                    </p>
                )}
                <p className="mt-2 text-xs text-muted-foreground">
                    Recorded by {prescription.created_by}
                </p>
            </div>
        </>
    );
}

PrescriptionShow.layout = {
    breadcrumbs: [{ title: 'Prescriptions', href: index() }],
};
