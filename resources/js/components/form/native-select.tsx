import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A plain <select> styled like the inputs. Used inside line tables, where it
 * is faster with the keyboard than a popover select.
 */
export function NativeSelect({
    className,
    ...props
}: ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'h-9 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 dark:bg-input/30',
                className,
            )}
            {...props}
        />
    );
}
