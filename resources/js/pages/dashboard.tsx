import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    CalendarClock,
    PackageX,
    ShoppingCart,
} from 'lucide-react';
import { useState } from 'react';
import { ExpiryBadge } from '@/components/expiry-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { read as readNotifications } from '@/routes/notifications';
import { screen } from '@/routes/pos';
import {
    index as stockIndex,
    batch as batchRoute,
    product as productRoute,
} from '@/routes/stock';

type Dashboard = {
    scope: 'branch' | 'mine';
    today: {
        invoices: number;
        net: string;
        discounts: string;
        by_hour: { hour: number; net: string }[];
    };
    week: { day: string; net: string }[];
    near_expiry?: {
        batch_id: number;
        name: string;
        lot: string;
        expires_on: string;
        quantity: number;
    }[];
    expired?: { batches: number; value: string };
    low_stock?: {
        product_id: number;
        name: string;
        on_hand: number;
        reorder_point: number;
    }[];
    pending?: Record<string, number>;
    notifications?: {
        id: string;
        data: { branch?: string; batches?: unknown[] };
        created_at: string;
    }[];
};

const pendingLabels: Record<string, string> = {
    purchase_orders_to_approve: 'Purchase orders to approve',
    adjustments_to_post: 'Adjustments to post',
    counts_to_post: 'Stock counts to post',
    transfers_to_receive: 'Transfers to receive',
    receipts_in_draft: 'Goods receipts in draft',
};

function StatTile({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 text-3xl font-semibold tabular-nums">
                {value}
            </div>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}

/**
 * Net sales for the last seven days, one hue, with a hover tooltip per bar
 * and a table view for screen readers and exact values.
 */
function WeekChart({ week }: { week: Dashboard['week'] }) {
    const [active, setActive] = useState<number | null>(null);
    const [asTable, setAsTable] = useState(false);
    const max = Math.max(1, ...week.map((day) => Number(day.net)));

    return (
        <section className="rounded-xl border bg-card p-4">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="font-medium">Net sales, last 7 days</h2>
                <button
                    type="button"
                    className="text-xs text-muted-foreground underline"
                    onClick={() => setAsTable(!asTable)}
                >
                    {asTable ? 'Show chart' : 'Show table'}
                </button>
            </div>
            {asTable ? (
                <table className="w-full text-sm">
                    <tbody>
                        {week.map((day) => (
                            <tr
                                key={day.day}
                                className="border-b last:border-0"
                            >
                                <td className="py-1">{formatDate(day.day)}</td>
                                <td className="py-1 text-right tabular-nums">
                                    {formatMoney(day.net)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <div className="relative">
                    <div
                        className="flex h-40 items-end gap-2 border-b border-border"
                        role="img"
                        aria-label="Bar chart of net sales for the last seven days"
                    >
                        {week.map((day, i) => (
                            <div
                                key={day.day}
                                className="relative flex h-full flex-1 items-end justify-center"
                                onMouseEnter={() => setActive(i)}
                                onMouseLeave={() => setActive(null)}
                            >
                                <div
                                    className={cn(
                                        'w-full max-w-10 rounded-t-[4px] bg-primary transition-opacity',
                                        active !== null &&
                                            active !== i &&
                                            'opacity-50',
                                    )}
                                    style={{
                                        height: `${(Number(day.net) / max) * 100}%`,
                                        minHeight: Number(day.net) > 0 ? 2 : 0,
                                    }}
                                />
                                {active === i && (
                                    <div className="pointer-events-none absolute bottom-full mb-1 rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap shadow-sm">
                                        <div className="text-muted-foreground">
                                            {formatDate(day.day)}
                                        </div>
                                        <div className="font-medium tabular-nums">
                                            {formatMoney(day.net)}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="mt-1 flex gap-2 text-center text-xs text-muted-foreground">
                        {week.map((day) => (
                            <div key={day.day} className="flex-1">
                                {new Date(
                                    `${day.day}T00:00:00`,
                                ).toLocaleDateString('en-PH', {
                                    weekday: 'short',
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}

export default function DashboardPage({
    dashboard: data,
}: {
    dashboard: Dashboard | null;
}) {
    const can = useCan();

    if (data === null) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="p-6 text-muted-foreground">
                    Your account has no branch yet. Ask the owner to assign one.
                </div>
            </>
        );
    }

    const pending = Object.entries(data.pending ?? {}).filter(
        ([, count]) => count > 0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {data.scope === 'mine' ? 'My sales today' : 'Today'}
                    </h1>
                    {can('pos.sell') && (
                        <Button asChild>
                            <Link href={screen()}>
                                <ShoppingCart /> Open POS
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile
                        label="Net sales"
                        value={formatMoney(data.today.net)}
                        hint={`${formatNumber(data.today.invoices)} invoices`}
                    />
                    <StatTile
                        label="Senior/PWD discounts"
                        value={formatMoney(data.today.discounts)}
                    />
                    {data.low_stock && (
                        <StatTile
                            label="At or below reorder point"
                            value={formatNumber(
                                data.low_stock.length >= 8
                                    ? '8+'
                                    : data.low_stock.length,
                            )}
                            hint="products"
                        />
                    )}
                    {data.expired && (
                        <StatTile
                            label="Expired stock on hand"
                            value={formatMoney(data.expired.value)}
                            hint={`${data.expired.batches} batch(es) to dispose`}
                        />
                    )}
                </div>

                {data.week.length > 0 && <WeekChart week={data.week} />}

                <div className="grid gap-4 lg:grid-cols-2">
                    {data.near_expiry && (
                        <section className="rounded-xl border bg-card p-4">
                            <h2 className="mb-3 flex items-center gap-2 font-medium">
                                <CalendarClock className="size-4" /> Expiring
                                within 90 days
                            </h2>
                            {data.near_expiry.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Nothing expiring soon.
                                </p>
                            )}
                            <ul className="space-y-2 text-sm">
                                {data.near_expiry.map((batch) => (
                                    <li
                                        key={batch.batch_id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <Link
                                            href={batchRoute(batch.batch_id)}
                                            className="hover:underline"
                                        >
                                            {batch.name}{' '}
                                            <span className="text-muted-foreground">
                                                · lot {batch.lot} ·{' '}
                                                {batch.quantity}
                                            </span>
                                        </Link>
                                        <ExpiryBadge date={batch.expires_on} />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {data.low_stock && (
                        <section className="rounded-xl border bg-card p-4">
                            <h2 className="mb-3 flex items-center gap-2 font-medium">
                                <PackageX className="size-4" /> Low stock
                            </h2>
                            {data.low_stock.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Everything is above its reorder point.
                                </p>
                            )}
                            <ul className="space-y-2 text-sm">
                                {data.low_stock.map((item) => (
                                    <li
                                        key={item.product_id}
                                        className="flex justify-between gap-2"
                                    >
                                        <Link
                                            href={productRoute(item.product_id)}
                                            className="hover:underline"
                                        >
                                            {item.name}
                                        </Link>
                                        <span className="text-muted-foreground tabular-nums">
                                            {item.on_hand} /{' '}
                                            {item.reorder_point}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <Link
                                href={stockIndex({
                                    query: { 'filter[view]': 'low' },
                                })}
                                className="mt-3 inline-block text-xs underline"
                            >
                                See all
                            </Link>
                        </section>
                    )}

                    {pending.length > 0 && (
                        <section className="rounded-xl border bg-card p-4">
                            <h2 className="mb-3 flex items-center gap-2 font-medium">
                                <AlertTriangle className="size-4" /> Waiting for
                                you
                            </h2>
                            <ul className="space-y-1 text-sm">
                                {pending.map(([key, count]) => (
                                    <li
                                        key={key}
                                        className="flex justify-between"
                                    >
                                        <span>{pendingLabels[key] ?? key}</span>
                                        <span className="font-medium tabular-nums">
                                            {count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {data.notifications && data.notifications.length > 0 && (
                        <section className="rounded-xl border bg-card p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="flex items-center gap-2 font-medium">
                                    <Bell className="size-4" /> Expiry alerts
                                </h2>
                                <button
                                    type="button"
                                    className="text-xs underline"
                                    onClick={() =>
                                        router.post(
                                            readNotifications().url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Mark all read
                                </button>
                            </div>
                            <ul className="space-y-1 text-sm">
                                {data.notifications.map((notification) => (
                                    <li key={notification.id}>
                                        {
                                            (notification.data.batches ?? [])
                                                .length
                                        }{' '}
                                        batch(es) reached an alert ·{' '}
                                        {formatDate(notification.created_at)}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}

DashboardPage.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
