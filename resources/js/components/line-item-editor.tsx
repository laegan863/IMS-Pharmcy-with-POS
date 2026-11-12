import { Trash2 } from 'lucide-react';
import { useRef } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { ProductPicker } from '@/components/product-picker';
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
import type { LookupProduct } from '@/types/product';

export type LineColumn<T> = {
    key: string;
    header: string;
    className?: string;
    /** Renders the cell; `index` is the line's position, for error keys. */
    cell: (
        line: T,
        index: number,
        update: (changes: Partial<T>) => void,
    ) => ReactNode;
};

type LineItemEditorProps<T> = {
    lines: T[];
    onChange: (lines: T[]) => void;
    columns: LineColumn<T>[];
    /** Builds a new line from a scanned or searched product. */
    makeLine: (product: LookupProduct, unitId: number | null) => T | null;
    /** Server validation errors keyed like "lines.0.quantity". */
    errors?: Record<string, string | undefined>;
    readOnly?: boolean;
    footer?: ReactNode;
    pickerPlaceholder?: string;
};

/**
 * The line table every stock document uses. Scan a barcode or search to add
 * a line; Tab moves across the fields; the picker keeps focus between scans
 * so a 40-line delivery needs no mouse. Each document supplies its own
 * columns (unit, quantity, lot, expiry, cost...).
 */
export function LineItemEditor<T>({
    lines,
    onChange,
    columns,
    makeLine,
    errors = {},
    readOnly = false,
    footer,
    pickerPlaceholder,
}: LineItemEditorProps<T>) {
    const picker = useRef<HTMLInputElement>(null);

    const update = (index: number, changes: Partial<T>) =>
        onChange(
            lines.map((line, i) =>
                i === index ? { ...line, ...changes } : line,
            ),
        );

    const remove = (index: number) => {
        onChange(lines.filter((_, i) => i !== index));
        picker.current?.focus();
    };

    const add = (product: LookupProduct, unitId: number | null) => {
        const line = makeLine(product, unitId);

        if (line) {
            onChange([...lines, line]);
        }
    };

    return (
        <div className="space-y-3">
            {!readOnly && (
                <ProductPicker
                    ref={picker}
                    onPick={add}
                    placeholder={pickerPlaceholder}
                    className="max-w-xl"
                />
            )}
            <InputError message={errors.lines} />
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-10">#</TableHead>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={column.className}
                                >
                                    {column.header}
                                </TableHead>
                            ))}
                            {!readOnly && <TableHead className="w-10" />}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {lines.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length + 2}
                                    className="h-16 text-center text-muted-foreground"
                                >
                                    No lines yet. Scan or search a product
                                    above.
                                </TableCell>
                            </TableRow>
                        )}
                        {lines.map((line, index) => (
                            <TableRow key={index} className="align-top">
                                <TableCell className="pt-4 text-muted-foreground">
                                    {index + 1}
                                </TableCell>
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={column.className}
                                    >
                                        {column.cell(line, index, (changes) =>
                                            update(index, changes),
                                        )}
                                        <InputError
                                            message={
                                                errors[
                                                    `lines.${index}.${column.key}`
                                                ]
                                            }
                                        />
                                    </TableCell>
                                ))}
                                {!readOnly && (
                                    <TableCell>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove line ${index + 1}`}
                                            onClick={() => remove(index)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                    {footer && <TableFooter>{footer}</TableFooter>}
                </Table>
            </div>
        </div>
    );
}
