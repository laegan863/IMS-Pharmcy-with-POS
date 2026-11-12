import { StatusBadge } from '@/components/status-badge';

const tones: Record<
    string,
    'neutral' | 'success' | 'warning' | 'danger' | 'info'
> = {
    draft: 'neutral',
    approved: 'info',
    partly_received: 'warning',
    received: 'success',
    posted: 'success',
    cancelled: 'danger',
    in_transit: 'warning',
    counting: 'info',
    reviewed: 'info',
    completed: 'success',
    held: 'warning',
    voided: 'danger',
    refunded: 'warning',
    open: 'info',
    closed: 'neutral',
};

/** The status of a stock or sales document. */
export function DocumentStatusBadge({
    status,
    label,
}: {
    status: string;
    label?: string;
}) {
    return (
        <StatusBadge tone={tones[status] ?? 'neutral'}>
            {label ?? status.replace(/_/g, ' ')}
        </StatusBadge>
    );
}
