import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

const toneClasses: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    success:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    warning:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    danger: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    info: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
};

/** A small coloured label for statuses such as Active, Posted or Expired. */
export function StatusBadge({
    tone = 'neutral',
    children,
}: {
    tone?: Tone;
    children: React.ReactNode;
}) {
    return (
        <Badge
            variant="outline"
            className={cn('border-transparent', toneClasses[tone])}
        >
            {children}
        </Badge>
    );
}
