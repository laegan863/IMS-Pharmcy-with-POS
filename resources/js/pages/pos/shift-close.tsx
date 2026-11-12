import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/format';
import { screen } from '@/routes/pos';
import { cash, close, count } from '@/routes/pos/shifts';

type Props = {
    shift: { id: number; number: string; opening_cash: string };
    denominations: string[];
};

export default function ShiftClose({ shift, denominations }: Props) {
    const [confirm, setConfirm] = useState(false);
    const form = useForm({
        denominations: Object.fromEntries(
            denominations.map((face) => [face, '']),
        ) as Record<string, string>,
    });
    const cashForm = useForm({ type: 'out', amount: '', reason: '' });

    const counted = denominations.reduce(
        (sum, face) =>
            sum + Number(face) * (Number(form.data.denominations[face]) || 0),
        0,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setConfirm(true);
    };

    const recordCash = (event: FormEvent) => {
        event.preventDefault();
        cashForm.post(cash().url, {
            preserveScroll: true,
            onSuccess: () => cashForm.reset(),
        });
    };

    return (
        <>
            <Head title="Close shift" />
            <div className="grid gap-10 px-4 py-6 lg:grid-cols-2">
                <div>
                    <PageHeader
                        title={`Close shift ${shift.number}`}
                        description={`Count the drawer by denomination. Opening float was ${formatMoney(shift.opening_cash)}. The expected amount is shown on the X-reading after closing.`}
                    />
                    <form onSubmit={submit} className="max-w-md space-y-4">
                        <div className="grid grid-cols-2 gap-x-6 gap-y-2">
                            {denominations.map((face) => (
                                <label
                                    key={face}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="w-16 text-right tabular-nums">
                                        ₱
                                        {Number(face) >= 1
                                            ? Number(face)
                                            : face}
                                    </span>
                                    <span className="text-muted-foreground">
                                        ×
                                    </span>
                                    <Input
                                        aria-label={`Pieces of ${face}`}
                                        inputMode="numeric"
                                        className="w-24"
                                        value={form.data.denominations[face]}
                                        onChange={(event) =>
                                            form.setData('denominations', {
                                                ...form.data.denominations,
                                                [face]: event.target.value,
                                            })
                                        }
                                    />
                                </label>
                            ))}
                        </div>
                        <div className="flex items-baseline justify-between border-t pt-3">
                            <span className="text-sm text-muted-foreground">
                                Counted
                            </span>
                            <span className="text-2xl font-semibold tabular-nums">
                                {formatMoney(counted)}
                            </span>
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                Close shift and print X-reading
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={screen()}>Back to POS</Link>
                            </Button>
                        </div>
                    </form>
                </div>

                <div>
                    <PageHeader
                        title="Cash in or out"
                        description="Record cash added to or taken from the drawer, other than sales."
                    />
                    <form onSubmit={recordCash} className="max-w-md space-y-4">
                        <div className="flex gap-2">
                            {(['in', 'out'] as const).map((type) => (
                                <Button
                                    key={type}
                                    type="button"
                                    variant={
                                        cashForm.data.type === type
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() =>
                                        cashForm.setData('type', type)
                                    }
                                >
                                    Cash {type}
                                </Button>
                            ))}
                        </div>
                        <TextField
                            id="cash-amount"
                            label="Amount (₱)"
                            inputMode="decimal"
                            value={cashForm.data.amount}
                            onChange={(value) =>
                                cashForm.setData('amount', value)
                            }
                            error={cashForm.errors.amount}
                            required
                        />
                        <TextField
                            id="cash-reason"
                            label="Reason"
                            value={cashForm.data.reason}
                            onChange={(value) =>
                                cashForm.setData('reason', value)
                            }
                            error={cashForm.errors.reason}
                            required
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={cashForm.processing}
                        >
                            Record
                        </Button>
                    </form>
                </div>
            </div>
            <ConfirmDialog
                open={confirm}
                onOpenChange={setConfirm}
                title="Close this shift?"
                description={`Counted cash: ${formatMoney(counted)}. You cannot sell on this shift after closing.`}
                confirmLabel="Close shift"
                processing={form.processing}
                onConfirm={() => form.post(close().url)}
            />
        </>
    );
}

ShiftClose.layout = {
    breadcrumbs: [{ title: 'Close shift', href: count() }],
};
