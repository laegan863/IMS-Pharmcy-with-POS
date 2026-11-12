import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/format';

/**
 * An expiry date coloured by how close it is: red once expired or within
 * 30 days, amber within 90 days.
 */
export function ExpiryBadge({ date }: { date: string | null }) {
    if (!date) {
        return <span className="text-muted-foreground">—</span>;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const days = Math.round(
        (new Date(`${date}T00:00:00`).getTime() - today.getTime()) / 86400000,
    );

    if (days < 0) {
        return (
            <StatusBadge tone="danger">Expired {formatDate(date)}</StatusBadge>
        );
    }

    if (days <= 30) {
        return <StatusBadge tone="danger">{formatDate(date)}</StatusBadge>;
    }

    if (days <= 90) {
        return <StatusBadge tone="warning">{formatDate(date)}</StatusBadge>;
    }

    return <span>{formatDate(date)}</span>;
}
