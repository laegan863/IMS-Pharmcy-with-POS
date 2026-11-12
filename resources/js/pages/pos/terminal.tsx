import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    BadgePercent,
    HelpCircle,
    LogOut,
    Minus,
    PauseCircle,
    PlayCircle,
    Plus,
    Receipt,
    Trash2,
    Wallet,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { GenericsDialog } from '@/components/pos/generics-dialog';
import { PrescriptionDialog } from '@/components/pos/prescription-dialog';
import { ProductPicker } from '@/components/product-picker';
import { StatusBadge } from '@/components/status-badge';
import { SupervisorApprovalDialog } from '@/components/supervisor-approval-dialog';
import type { Approval } from '@/components/supervisor-approval-dialog';
import { startTour, Tour } from '@/components/tour';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { http, HttpError } from '@/lib/http';
import { posTour } from '@/lib/tours';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes';
import { quote as quoteRoute } from '@/routes/pos';
import {
    index as heldIndex,
    resume as resumeRoute,
    store as holdRoute,
} from '@/routes/pos/held';
import { complete, index as salesIndex } from '@/routes/pos/sales';
import { count as closeShift } from '@/routes/pos/shifts';
import type { LookupProduct, LookupUnit } from '@/types/product';

type DiscountType = 'none' | 'senior' | 'pwd';

type CartLine = {
    key: string;
    product_id: number;
    product_unit_id: number;
    name: string;
    classification: LookupProduct['classification'];
    units: LookupUnit[];
    quantity: number;
    /** Price override; needs a supervisor. */
    unit_price: string | null;
    /** The prescription line this Rx item fills, and its label. */
    prescription_line_id?: number | null;
    prescription_label?: string | null;
};

type Discount = {
    type: DiscountType;
    id_number: string;
    holder_name: string;
};

type QuoteLine = {
    product_id: number;
    product_unit_id: number;
    description: string;
    quantity: number;
    unit_price: string;
    list_price: string;
    gross_amount: string;
    discount_amount: string;
    line_total: string;
    statutory_discount_applied: boolean;
    available: number;
    quantity_base: number;
};

type Totals = {
    gross_amount: string;
    vatable_amount: string;
    vat_amount: string;
    vat_exempt_amount: string;
    zero_rated_amount: string;
    discount_amount: string;
    line_total: string;
};

type PaymentRow = { method: string; amount: string; reference: string };

type Props = {
    shift: { id: number; number: string; terminal: string; opened_at: string };
    paymentMethods: Record<string, string>;
    idleTimeoutMinutes: number;
    heldCount: number;
};

const emptyDiscount: Discount = {
    type: 'none',
    id_number: '',
    holder_name: '',
};

const newUuid = () => crypto.randomUUID();

export default function PosTerminal({
    shift,
    paymentMethods,
    idleTimeoutMinutes,
    heldCount: initialHeldCount,
}: Props) {
    const [lines, setLines] = useState<CartLine[]>([]);
    const [discount, setDiscount] = useState<Discount>(emptyDiscount);
    const [approval, setApproval] = useState<Approval | null>(null);
    const [clientUuid, setClientUuid] = useState(newUuid);
    const [quote, setQuote] = useState<{
        lines: QuoteLine[];
        totals: Totals | null;
    }>({ lines: [], totals: null });
    const [quoteError, setQuoteError] = useState<string | null>(null);
    const [payments, setPayments] = useState<PaymentRow[]>([
        { method: 'cash', amount: '', reference: '' },
    ]);
    const [error, setError] = useState<string | null>(null);
    const [posting, setPosting] = useState(false);
    /** Set when the connection dropped mid-post: the sale may or may not exist. */
    const [uncertain, setUncertain] = useState(false);
    const [lastSale, setLastSale] = useState<{
        number: string;
        change_due: string;
        receipt_url: string;
    } | null>(null);
    const [dialog, setDialog] = useState<
        | 'discount'
        | 'resume'
        | 'override'
        | 'rx'
        | 'generics'
        | 'approval'
        | null
    >(null);
    /** The cart line a prescription or generics dialog is open for. */
    const [activeLine, setActiveLine] = useState<CartLine | null>(null);
    const [overrideLine, setOverrideLine] = useState<{
        key: string;
        price: string;
    } | null>(null);
    const [held, setHeld] = useState<{ id: number; label: string }[]>([]);
    const [heldCount, setHeldCount] = useState(initialHeldCount);
    const picker = useRef<HTMLInputElement>(null);
    const cashInput = useRef<HTMLInputElement>(null);

    const editable = !posting && !uncertain;
    const total = quote.totals?.line_total ?? '0.00';

    // Price the cart on the server whenever it changes.
    useEffect(() => {
        if (lines.length === 0) {
            setQuote({ lines: [], totals: null });
            setQuoteError(null);

            return;
        }

        const timer = window.setTimeout(async () => {
            try {
                const result = await http.post<{
                    lines: QuoteLine[];
                    totals: Totals;
                }>(quoteRoute().url, {
                    discount_type: discount.type,
                    lines: lines.map((line) => ({
                        product_id: line.product_id,
                        product_unit_id: line.product_unit_id,
                        quantity: line.quantity,
                        unit_price: line.unit_price,
                    })),
                });
                setQuote(result);
                setQuoteError(null);
            } catch (exception) {
                setQuoteError(
                    exception instanceof HttpError
                        ? exception.firstMessage()
                        : 'Could not price the cart.',
                );
            }
        }, 120);

        return () => window.clearTimeout(timer);
    }, [lines, discount.type]);

    const resetSale = useCallback(() => {
        setLines([]);
        setDiscount(emptyDiscount);
        setApproval(null);
        setPayments([{ method: 'cash', amount: '', reference: '' }]);
        setClientUuid(newUuid());
        setError(null);
        setUncertain(false);
        picker.current?.focus();
    }, []);

    const addProduct = (product: LookupProduct, unitId: number | null) => {
        if (!editable) {
            return;
        }

        const sellable = product.units.filter(
            (unit) => unit.is_sellable && unit.price !== null,
        );
        const unit =
            sellable.find((u) => u.id === unitId) ??
            sellable.find((u) => u.is_base) ??
            sellable[0];

        if (!unit) {
            setError(`${product.name} has no selling price.`);

            return;
        }

        setLastSale(null);
        setError(null);
        setLines((current) => {
            const existing = current.findIndex(
                (line) =>
                    line.product_id === product.id &&
                    line.product_unit_id === unit.id &&
                    line.unit_price === null,
            );

            if (existing >= 0) {
                return current.map((line, i) =>
                    i === existing
                        ? { ...line, quantity: line.quantity + 1 }
                        : line,
                );
            }

            return [
                ...current,
                {
                    key: `${product.id}-${unit.id}-${Date.now()}`,
                    product_id: product.id,
                    product_unit_id: unit.id,
                    name: product.name,
                    classification: product.classification,
                    units: sellable,
                    quantity: 1,
                    unit_price: null,
                },
            ];
        });
    };

    const updateLine = (key: string, changes: Partial<CartLine>) =>
        setLines((current) =>
            current.map((line) =>
                line.key === key ? { ...line, ...changes } : line,
            ),
        );

    const removeLine = (key: string) => {
        setLines((current) => current.filter((line) => line.key !== key));
        picker.current?.focus();
    };

    const paid = payments.reduce(
        (sum, payment) => sum + (Number(payment.amount) || 0),
        0,
    );
    const change = Math.max(0, paid - Number(total));

    const completeSale = async () => {
        if (lines.length === 0 || posting) {
            return;
        }

        const tendered = payments.filter(
            (payment) => Number(payment.amount) > 0,
        );

        if (tendered.length === 0) {
            // Exact cash by default.
            tendered.push({ method: 'cash', amount: total, reference: '' });
        }

        setPosting(true);
        setError(null);

        try {
            const sale = await http.post<{
                number: string;
                change_due: string;
                receipt_url: string;
            }>(complete().url, {
                client_uuid: clientUuid,
                lines: lines.map((line) => ({
                    product_id: line.product_id,
                    product_unit_id: line.product_unit_id,
                    quantity: line.quantity,
                    unit_price: line.unit_price,
                    prescription_line_id: line.prescription_line_id ?? null,
                })),
                discount_type: discount.type,
                discount_id_number: discount.id_number || null,
                discount_holder_name: discount.holder_name || null,
                payments: tendered.map((payment) => ({
                    method: payment.method,
                    amount: payment.amount,
                    reference: payment.reference || null,
                })),
                approver: approval?.approver ?? null,
                approver_pin: approval?.approver_pin ?? null,
            });

            window.open(sale.receipt_url, '_blank', 'width=420,height=700');
            setLastSale(sale);
            resetSale();
        } catch (exception) {
            if (exception instanceof HttpError && exception.status === 0) {
                // We do not know if it was saved. Keep the same cart and id so
                // a retry returns the saved sale instead of posting twice.
                setUncertain(true);
                setError(exception.message);
            } else if (
                exception instanceof HttpError &&
                'approver_pin' in exception.errors &&
                approval === null
            ) {
                // A dangerous drug or override needs the pharmacist's PIN.
                setError(exception.firstMessage());
                setDialog('approval');
            } else {
                setError(
                    exception instanceof HttpError
                        ? exception.firstMessage()
                        : 'The sale could not be posted.',
                );
            }
        } finally {
            setPosting(false);
        }
    };

    const holdSale = async () => {
        if (lines.length === 0 || !editable) {
            return;
        }

        const label = window.prompt(
            'Name for this held sale (e.g. customer name):',
            `Held ${new Date().toLocaleTimeString('en-PH')}`,
        );

        if (!label) {
            return;
        }

        await http.post(holdRoute().url, { label, cart: { lines, discount } });
        setHeldCount((count) => count + 1);
        resetSale();
    };

    const openResume = async () => {
        if (!editable) {
            return;
        }

        const result = await http.get<{
            data: { id: number; label: string }[];
        }>(heldIndex().url);
        setHeld(result.data);
        setDialog('resume');
    };

    const resumeHeld = async (id: number) => {
        const result = await http.post<{
            cart: { lines: CartLine[]; discount: Discount };
        }>(resumeRoute(id).url);
        setLines(result.cart.lines);
        setDiscount(result.cart.discount ?? emptyDiscount);
        setClientUuid(newUuid());
        setHeldCount((count) => Math.max(0, count - 1));
        setDialog(null);
        picker.current?.focus();
    };

    // Keyboard shortcuts.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const shortcuts: Record<string, () => void> = {
                F2: () => picker.current?.focus(),
                F4: () => editable && setDialog('discount'),
                F7: () => void holdSale(),
                F8: () => void openResume(),
                F9: () => cashInput.current?.focus(),
                F12: () => void completeSale(),
            };

            const action = shortcuts[event.key];

            if (action) {
                event.preventDefault();
                action();
            }

            if (
                event.key === 'Delete' &&
                event.ctrlKey &&
                editable &&
                lines.length > 0
            ) {
                event.preventDefault();
                removeLine(lines[lines.length - 1].key);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    // Sign out after the idle timeout (setting pos.idle_timeout_minutes).
    useEffect(() => {
        let timer = 0;
        const reset = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(
                () => router.post(logout().url),
                idleTimeoutMinutes * 60 * 1000,
            );
        };

        const events = ['keydown', 'mousedown', 'touchstart'];
        events.forEach((name) => window.addEventListener(name, reset));
        reset();

        return () => {
            window.clearTimeout(timer);
            events.forEach((name) => window.removeEventListener(name, reset));
        };
    }, [idleTimeoutMinutes]);

    const quoteFor = (index: number): QuoteLine | undefined =>
        quote.lines[index];

    return (
        <>
            <Head title="POS" />
            <div className="flex h-screen flex-col bg-muted/40">
                <header className="flex flex-wrap items-center gap-3 border-b bg-background px-4 py-2 text-sm">
                    <Link href={dashboard()} className="font-semibold">
                        POS
                    </Link>
                    <span className="text-muted-foreground">
                        {shift.terminal} · Shift {shift.number}
                    </span>
                    <div
                        data-tour="pos-actions"
                        className="ml-auto flex flex-wrap gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            aria-label="Take the tour"
                            title="Take the tour"
                            onClick={startTour}
                        >
                            <HelpCircle />
                        </Button>
                        <Button size="sm" variant="outline" asChild>
                            <Link href={salesIndex()}>
                                <Receipt /> Invoices
                            </Link>
                        </Button>
                        <Button size="sm" variant="outline" asChild>
                            <Link href={closeShift()}>
                                <LogOut /> Close shift
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="grid flex-1 gap-4 overflow-hidden p-4 lg:grid-cols-[1fr_380px]">
                    <section className="flex min-h-0 flex-col gap-3">
                        <div data-tour="pos-search">
                            <ProductPicker
                                ref={picker}
                                onPick={addProduct}
                                autoFocus
                                placeholder="Scan barcode or type brand / generic name  (F2)"
                            />
                        </div>

                        {uncertain && (
                            <div className="flex items-center gap-2 rounded-md border border-amber-400 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                <AlertTriangle className="size-4" />
                                The connection dropped while posting. The cart
                                is locked; press F12 to try again. It will not
                                post twice.
                            </div>
                        )}

                        <div
                            data-tour="pos-cart"
                            className="min-h-0 flex-1 overflow-y-auto rounded-md border bg-background"
                        >
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-background text-left text-xs text-muted-foreground">
                                    <tr className="border-b">
                                        <th className="p-2">Item</th>
                                        <th className="p-2">Unit</th>
                                        <th className="p-2">Qty</th>
                                        <th className="p-2 text-right">
                                            Price
                                        </th>
                                        <th className="p-2 text-right">
                                            Total
                                        </th>
                                        <th className="w-8" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="p-8 text-center text-muted-foreground"
                                            >
                                                Scan the first item.
                                            </td>
                                        </tr>
                                    )}
                                    {lines.map((line, index) => {
                                        const priced = quoteFor(index);
                                        const short =
                                            priced &&
                                            priced.quantity_base >
                                                priced.available;

                                        return (
                                            <tr
                                                key={line.key}
                                                className={cn(
                                                    'border-b align-top',
                                                    short &&
                                                        'bg-red-50 dark:bg-red-950/40',
                                                )}
                                            >
                                                <td className="p-2">
                                                    <div className="font-medium">
                                                        {line.name}
                                                    </div>
                                                    <div className="mt-0.5 flex gap-1">
                                                        {(line.classification ===
                                                            'rx' ||
                                                            line.classification ===
                                                                'dangerous_drug') && (
                                                            <StatusBadge
                                                                tone={
                                                                    line.classification ===
                                                                    'rx'
                                                                        ? 'warning'
                                                                        : 'danger'
                                                                }
                                                            >
                                                                {line.classification ===
                                                                'rx'
                                                                    ? 'Rx'
                                                                    : 'S2'}
                                                            </StatusBadge>
                                                        )}
                                                        {(line.classification ===
                                                            'rx' ||
                                                            line.classification ===
                                                                'dangerous_drug') && (
                                                            <button
                                                                type="button"
                                                                className="text-xs underline underline-offset-2"
                                                                disabled={
                                                                    !editable
                                                                }
                                                                onClick={() => {
                                                                    setActiveLine(
                                                                        line,
                                                                    );
                                                                    setDialog(
                                                                        'rx',
                                                                    );
                                                                }}
                                                            >
                                                                {line.prescription_label ??
                                                                    'Attach prescription'}
                                                            </button>
                                                        )}
                                                        {line.classification !==
                                                            'non_drug' && (
                                                            <button
                                                                type="button"
                                                                className="text-xs text-muted-foreground underline underline-offset-2"
                                                                disabled={
                                                                    !editable
                                                                }
                                                                onClick={() => {
                                                                    setActiveLine(
                                                                        line,
                                                                    );
                                                                    setDialog(
                                                                        'generics',
                                                                    );
                                                                }}
                                                            >
                                                                Generics
                                                            </button>
                                                        )}
                                                        {priced?.statutory_discount_applied && (
                                                            <StatusBadge tone="info">
                                                                20% off
                                                            </StatusBadge>
                                                        )}
                                                        {short && (
                                                            <StatusBadge tone="danger">
                                                                Only{' '}
                                                                {
                                                                    priced.available
                                                                }{' '}
                                                                in stock
                                                            </StatusBadge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-2">
                                                    <select
                                                        aria-label="Unit"
                                                        className="h-8 rounded-md border bg-transparent px-1"
                                                        value={
                                                            line.product_unit_id
                                                        }
                                                        disabled={!editable}
                                                        onChange={(event) =>
                                                            updateLine(
                                                                line.key,
                                                                {
                                                                    product_unit_id:
                                                                        Number(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    unit_price:
                                                                        null,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {line.units.map(
                                                            (unit) => (
                                                                <option
                                                                    key={
                                                                        unit.id
                                                                    }
                                                                    value={
                                                                        unit.id
                                                                    }
                                                                >
                                                                    {unit.name}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </td>
                                                <td className="p-2">
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7"
                                                            aria-label="One less"
                                                            disabled={
                                                                !editable ||
                                                                line.quantity <=
                                                                    1
                                                            }
                                                            onClick={() =>
                                                                updateLine(
                                                                    line.key,
                                                                    {
                                                                        quantity:
                                                                            line.quantity -
                                                                            1,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Minus />
                                                        </Button>
                                                        <Input
                                                            aria-label="Quantity"
                                                            inputMode="numeric"
                                                            className="h-8 w-14 text-center"
                                                            value={
                                                                line.quantity
                                                            }
                                                            disabled={!editable}
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    line.key,
                                                                    {
                                                                        quantity:
                                                                            Math.max(
                                                                                1,
                                                                                Number(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                    1,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7"
                                                            aria-label="One more"
                                                            disabled={!editable}
                                                            onClick={() =>
                                                                updateLine(
                                                                    line.key,
                                                                    {
                                                                        quantity:
                                                                            line.quantity +
                                                                            1,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Plus />
                                                        </Button>
                                                    </div>
                                                </td>
                                                <td className="p-2 text-right">
                                                    <button
                                                        type="button"
                                                        className="underline-offset-2 hover:underline disabled:no-underline"
                                                        title="Override price (needs a supervisor)"
                                                        disabled={!editable}
                                                        onClick={() => {
                                                            setOverrideLine({
                                                                key: line.key,
                                                                price:
                                                                    priced?.unit_price ??
                                                                    '',
                                                            });
                                                            setDialog(
                                                                'override',
                                                            );
                                                        }}
                                                    >
                                                        {formatMoney(
                                                            priced?.unit_price,
                                                        )}
                                                    </button>
                                                    {line.unit_price !==
                                                        null && (
                                                        <div className="text-xs text-muted-foreground line-through">
                                                            {formatMoney(
                                                                priced?.list_price,
                                                            )}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="p-2 text-right font-medium tabular-nums">
                                                    {formatMoney(
                                                        priced?.line_total,
                                                    )}
                                                    {priced &&
                                                        Number(
                                                            priced.discount_amount,
                                                        ) > 0 && (
                                                            <div className="text-xs text-muted-foreground">
                                                                −
                                                                {formatMoney(
                                                                    priced.discount_amount,
                                                                )}
                                                            </div>
                                                        )}
                                                </td>
                                                <td className="p-2">
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        aria-label="Remove"
                                                        disabled={!editable}
                                                        onClick={() =>
                                                            removeLine(line.key)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            F2 search · F4 discount · F7 hold · F8 resume (
                            {heldCount}) · F9 payment · F12 complete · Ctrl+Del
                            remove last line
                        </p>
                    </section>

                    <aside className="flex min-h-0 flex-col gap-3 overflow-y-auto rounded-md border bg-background p-4">
                        <div data-tour="pos-discount" className="flex gap-2">
                            <Button
                                variant={
                                    discount.type === 'none'
                                        ? 'outline'
                                        : 'default'
                                }
                                className="flex-1"
                                disabled={!editable}
                                onClick={() => setDialog('discount')}
                            >
                                <BadgePercent />
                                {discount.type === 'none'
                                    ? 'Discount (F4)'
                                    : discount.type === 'senior'
                                      ? 'Senior citizen'
                                      : 'PWD'}
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Hold sale (F7)"
                                disabled={lines.length === 0 || !editable}
                                onClick={() => void holdSale()}
                            >
                                <PauseCircle />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Resume held sale (F8)"
                                disabled={!editable}
                                onClick={() => void openResume()}
                            >
                                <PlayCircle />
                            </Button>
                        </div>

                        <dl className="space-y-1 text-sm">
                            <Row
                                label="Gross"
                                value={quote.totals?.gross_amount}
                            />
                            {Number(quote.totals?.discount_amount) > 0 && (
                                <Row
                                    label="Senior/PWD discount"
                                    value={quote.totals?.discount_amount}
                                    negative
                                />
                            )}
                            <Row
                                label="VATable sales"
                                value={quote.totals?.vatable_amount}
                                muted
                            />
                            <Row
                                label="VAT"
                                value={quote.totals?.vat_amount}
                                muted
                            />
                            <Row
                                label="VAT-exempt"
                                value={quote.totals?.vat_exempt_amount}
                                muted
                            />
                            {Number(quote.totals?.zero_rated_amount) > 0 && (
                                <Row
                                    label="Zero-rated"
                                    value={quote.totals?.zero_rated_amount}
                                    muted
                                />
                            )}
                        </dl>
                        <div
                            data-tour="pos-totals"
                            className="flex items-baseline justify-between border-t pt-3"
                        >
                            <span className="text-sm text-muted-foreground">
                                Total due
                            </span>
                            <span className="text-3xl font-semibold tabular-nums">
                                {formatMoney(total)}
                            </span>
                        </div>
                        {quoteError && (
                            <p className="text-sm text-destructive">
                                {quoteError}
                            </p>
                        )}

                        <div
                            data-tour="pos-payment"
                            className="space-y-2 border-t pt-3"
                        >
                            {payments.map((payment, index) => (
                                <div key={index} className="flex gap-2">
                                    <select
                                        aria-label="Payment method"
                                        className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                        value={payment.method}
                                        disabled={!editable}
                                        onChange={(event) =>
                                            setPayments(
                                                payments.map((p, i) =>
                                                    i === index
                                                        ? {
                                                              ...p,
                                                              method: event
                                                                  .target.value,
                                                          }
                                                        : p,
                                                ),
                                            )
                                        }
                                    >
                                        {Object.entries(paymentMethods).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <Input
                                        ref={
                                            index === 0 ? cashInput : undefined
                                        }
                                        aria-label="Amount tendered"
                                        inputMode="decimal"
                                        placeholder={
                                            index === 0 ? total : '0.00'
                                        }
                                        value={payment.amount}
                                        disabled={!editable}
                                        onChange={(event) =>
                                            setPayments(
                                                payments.map((p, i) =>
                                                    i === index
                                                        ? {
                                                              ...p,
                                                              amount: event
                                                                  .target.value,
                                                          }
                                                        : p,
                                                ),
                                            )
                                        }
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                void completeSale();
                                            }
                                        }}
                                    />
                                    {payment.method !== 'cash' && (
                                        <Input
                                            aria-label="Reference number"
                                            placeholder="Ref. no."
                                            value={payment.reference}
                                            disabled={!editable}
                                            onChange={(event) =>
                                                setPayments(
                                                    payments.map((p, i) =>
                                                        i === index
                                                            ? {
                                                                  ...p,
                                                                  reference:
                                                                      event
                                                                          .target
                                                                          .value,
                                                              }
                                                            : p,
                                                    ),
                                                )
                                            }
                                        />
                                    )}
                                </div>
                            ))}
                            <Button
                                variant="ghost"
                                size="sm"
                                disabled={!editable || payments.length >= 3}
                                onClick={() =>
                                    setPayments([
                                        ...payments,
                                        {
                                            method: 'ewallet',
                                            amount: '',
                                            reference: '',
                                        },
                                    ])
                                }
                            >
                                <Wallet /> Split payment
                            </Button>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Change
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatMoney(change)}
                                </span>
                            </div>
                        </div>

                        {error && (
                            <p className="text-sm text-destructive">{error}</p>
                        )}

                        <Button
                            size="lg"
                            data-tour="pos-complete"
                            className="mt-auto h-14 text-lg"
                            disabled={
                                lines.length === 0 ||
                                posting ||
                                Boolean(quoteError)
                            }
                            onClick={() => void completeSale()}
                        >
                            {posting
                                ? 'Posting…'
                                : uncertain
                                  ? 'Retry posting (F12)'
                                  : 'Complete sale (F12)'}
                        </Button>

                        {lastSale && (
                            <div className="rounded-md border border-emerald-400 bg-emerald-50 p-3 text-sm dark:bg-emerald-950">
                                <div className="font-medium">
                                    Invoice {lastSale.number} posted
                                </div>
                                <div className="text-2xl font-semibold">
                                    Change {formatMoney(lastSale.change_due)}
                                </div>
                                <a
                                    className="underline"
                                    href={lastSale.receipt_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Print receipt again
                                </a>
                            </div>
                        )}
                    </aside>
                </div>
            </div>

            {activeLine && (
                <PrescriptionDialog
                    open={dialog === 'rx'}
                    productId={activeLine.product_id}
                    productName={activeLine.name}
                    dangerous={activeLine.classification === 'dangerous_drug'}
                    quantityBase={
                        activeLine.quantity *
                        (activeLine.units.find(
                            (unit) => unit.id === activeLine.product_unit_id,
                        )?.conversion_factor ?? 1)
                    }
                    onClose={() => setDialog(null)}
                    onPick={(picked) => {
                        updateLine(activeLine.key, {
                            prescription_line_id: picked.lineId,
                            prescription_label: picked.label,
                        });
                        setDialog(null);
                        picker.current?.focus();
                    }}
                />
            )}

            {activeLine && (
                <GenericsDialog
                    open={dialog === 'generics'}
                    productId={activeLine.product_id}
                    productName={activeLine.name}
                    onClose={() => setDialog(null)}
                    onSwap={(alternative) => {
                        const factor =
                            activeLine.units.find(
                                (unit) =>
                                    unit.id === activeLine.product_unit_id,
                            )?.conversion_factor ?? 1;
                        updateLine(activeLine.key, {
                            product_id: alternative.id,
                            product_unit_id: alternative.base_unit_id,
                            name: alternative.name,
                            units: [
                                {
                                    id: alternative.base_unit_id,
                                    name: alternative.base_unit,
                                    conversion_factor: 1,
                                    is_base: true,
                                    is_sellable: true,
                                    is_purchasable: false,
                                    price: alternative.base_price,
                                },
                            ],
                            quantity: activeLine.quantity * factor,
                            unit_price: null,
                        });
                        setDialog(null);
                        picker.current?.focus();
                    }}
                />
            )}

            <SupervisorApprovalDialog
                open={dialog === 'approval'}
                onOpenChange={(open) => !open && setDialog(null)}
                title="Pharmacist approval"
                description="This sale needs a pharmacist or supervisor (dangerous drug or price override)."
                onApprove={(given) => {
                    setApproval(given);
                    setDialog(null);
                }}
            />

            <DiscountDialog
                open={dialog === 'discount'}
                discount={discount}
                onClose={() => {
                    setDialog(null);
                    picker.current?.focus();
                }}
                onSave={(next) => {
                    setDiscount(next);
                    setDialog(null);
                    picker.current?.focus();
                }}
            />

            <Dialog
                open={dialog === 'resume'}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Resume a held sale</DialogTitle>
                    </DialogHeader>
                    {held.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No held sales on this terminal.
                        </p>
                    )}
                    <div className="space-y-2">
                        {held.map((item) => (
                            <Button
                                key={item.id}
                                variant="outline"
                                className="w-full justify-start"
                                onClick={() => void resumeHeld(item.id)}
                            >
                                {item.label}
                            </Button>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>

            <SupervisorApprovalDialog
                open={dialog === 'override'}
                onOpenChange={(open) => !open && setDialog(null)}
                title="Override price"
                description="Enter the new price for one unit. A supervisor approves it; the original price stays on record."
                onApprove={(given) => {
                    if (overrideLine) {
                        updateLine(overrideLine.key, {
                            unit_price: overrideLine.price,
                        });
                    }

                    setApproval(given);
                    setDialog(null);
                    picker.current?.focus();
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="override-price">New price (₱)</Label>
                    <Input
                        id="override-price"
                        inputMode="decimal"
                        value={overrideLine?.price ?? ''}
                        onChange={(event) =>
                            setOverrideLine(
                                overrideLine
                                    ? {
                                          ...overrideLine,
                                          price: event.target.value,
                                      }
                                    : null,
                            )
                        }
                    />
                </div>
            </SupervisorApprovalDialog>

            <Tour tourKey="pos" steps={posTour} />
        </>
    );
}

function Row({
    label,
    value,
    muted = false,
    negative = false,
}: {
    label: string;
    value?: string;
    muted?: boolean;
    /** Shown with a minus sign, for deductions. */
    negative?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex justify-between',
                muted && 'text-muted-foreground',
            )}
        >
            <dt>{label}</dt>
            <dd className="tabular-nums">
                {negative ? '−' : ''}
                {formatMoney(value ?? '0')}
            </dd>
        </div>
    );
}

function DiscountDialog({
    open,
    discount,
    onClose,
    onSave,
}: {
    open: boolean;
    discount: Discount;
    onClose: () => void;
    onSave: (discount: Discount) => void;
}) {
    const [draft, setDraft] = useState(discount);

    useEffect(() => {
        if (open) {
            setDraft(discount);
        }
    }, [open, discount]);

    const valid =
        draft.type === 'none' ||
        (draft.id_number.trim() !== '' && draft.holder_name.trim() !== '');

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Senior citizen or PWD discount</DialogTitle>
                    <DialogDescription>
                        20% off eligible medicines, with VAT removed first. One
                        discount per sale: a customer who is both a senior and a
                        PWD chooses one.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex gap-2">
                    {(['none', 'senior', 'pwd'] as DiscountType[]).map(
                        (type) => (
                            <Button
                                key={type}
                                type="button"
                                variant={
                                    draft.type === type ? 'default' : 'outline'
                                }
                                className="flex-1"
                                onClick={() => setDraft({ ...draft, type })}
                            >
                                {type === 'none'
                                    ? 'None'
                                    : type === 'senior'
                                      ? 'Senior citizen'
                                      : 'PWD'}
                            </Button>
                        ),
                    )}
                </div>
                {draft.type !== 'none' && (
                    <div className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor="discount-id">
                                {draft.type === 'senior'
                                    ? 'OSCA / senior citizen ID no.'
                                    : 'PWD ID no.'}
                            </Label>
                            <Input
                                id="discount-id"
                                autoFocus
                                value={draft.id_number}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        id_number: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="discount-name">
                                Name on the ID
                            </Label>
                            <Input
                                id="discount-name"
                                value={draft.holder_name}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        holder_name: event.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>
                )}
                <DialogFooter>
                    <Button
                        type="button"
                        disabled={!valid}
                        onClick={() =>
                            onSave(
                                draft.type === 'none' ? emptyDiscount : draft,
                            )
                        }
                    >
                        Apply
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
