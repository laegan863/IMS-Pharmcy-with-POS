import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FieldProps = {
    /** Visible label. */
    label: string;
    /** id of the input the label points at. */
    htmlFor: string;
    /** Server validation message for this field, if any. */
    error?: string;
    /** Short hint shown under the input. */
    help?: string | null;
    /** Marks the label as required. */
    required?: boolean;
    className?: string;
    children: ReactNode;
};

/**
 * Wraps one form input with its label, hint and server validation error.
 * Every data-entry form uses it so errors look the same everywhere.
 */
export function Field({
    label,
    htmlFor,
    error,
    help,
    required = false,
    className,
    children,
}: FieldProps) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden>
                        *
                    </span>
                )}
            </Label>
            {children}
            {help && <p className="text-xs text-muted-foreground">{help}</p>}
            <InputError message={error} />
        </div>
    );
}
