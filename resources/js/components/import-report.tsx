import { CheckCircle2, FileWarning } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type ImportError = { row: number; column: string; message: string };

/**
 * Result of an all-or-nothing spreadsheet import: a success summary, or every
 * problem by row and column so the file can be fixed and uploaded again.
 */
export function ImportReport({
    errors,
    success,
}: {
    errors: ImportError[];
    success: ReactNode;
}) {
    if (errors.length === 0) {
        return (
            <Alert>
                <CheckCircle2 />
                <AlertTitle>Import finished</AlertTitle>
                <AlertDescription>{success}</AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-3">
            <Alert variant="destructive">
                <FileWarning />
                <AlertTitle>
                    Nothing was imported: {errors.length} problem
                    {errors.length === 1 ? '' : 's'} found
                </AlertTitle>
                <AlertDescription>
                    Fix these rows in the file and upload it again.
                </AlertDescription>
            </Alert>
            <div className="max-h-[28rem] overflow-y-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-20">Row</TableHead>
                            <TableHead className="w-48">Column</TableHead>
                            <TableHead>Problem</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {errors.map((error, i) => (
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
    );
}
