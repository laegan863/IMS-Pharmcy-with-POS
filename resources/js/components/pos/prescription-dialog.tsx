import { useEffect, useState } from 'react';
import { Field } from '@/components/form/field';
import {
    emptyPrescriber,
    PrescriberFields,
} from '@/components/prescriber-fields';
import type { PrescriberDetails } from '@/components/prescriber-fields';
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
import { formatDate } from '@/lib/format';
import { http, HttpError } from '@/lib/http';
import { openLines, store } from '@/routes/pos/rx';

type OpenLine = {
    id: number;
    number: string;
    patient_name: string;
    prescriber: string;
    prescribed_on: string;
    product: string;
    balance: number;
};

export type PickedPrescription = { lineId: number; label: string };

/**
 * Links an Rx or S2 line on the POS to a prescription: pick one with a
 * balance left, or record the prescription the customer brought.
 */
export function PrescriptionDialog({
    open,
    productId,
    productName,
    dangerous,
    quantityBase,
    onClose,
    onPick,
}: {
    open: boolean;
    productId: number;
    productName: string;
    dangerous: boolean;
    quantityBase: number;
    onClose: () => void;
    onPick: (picked: PickedPrescription) => void;
}) {
    const [lines, setLines] = useState<OpenLine[]>([]);
    const [creating, setCreating] = useState(false);
    const [prescriber, setPrescriber] =
        useState<PrescriberDetails>(emptyPrescriber);
    const [form, setForm] = useState({
        patient_name: '',
        patient_address: '',
        prescribed_on: new Date().toISOString().slice(0, 10),
        special_form_serial: '',
        quantity_prescribed_base: String(quantityBase),
    });
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        setCreating(false);
        setError(null);
        void http
            .get<{ data: OpenLine[] }>(
                openLines({ query: { product_id: productId } }).url,
            )
            .then((result) => setLines(result.data));
    }, [open, productId]);

    // What still blocks saving, shown under the form so the button never
    // looks dead without a reason.
    const missing = [
        !form.patient_name.trim() && 'enter the patient name',
        !prescriber.prescriber_name.trim() && "enter the doctor's name",
        dangerous &&
            !prescriber.prescriber_s2_number.trim() &&
            "enter the doctor's S2 licence no.",
        !(Number(form.quantity_prescribed_base) > 0) &&
            'enter the quantity prescribed',
        dangerous &&
            !form.special_form_serial.trim() &&
            'enter the special form serial no.',
    ].filter((reason): reason is string => Boolean(reason));

    const save = async () => {
        setError(null);

        try {
            const result = await http.post<{
                prescription_line_id: number;
                number: string;
            }>(store().url, {
                ...form,
                ...prescriber,
                product_id: productId,
            });
            onPick({
                lineId: result.prescription_line_id,
                label: `${result.number} · ${form.patient_name}`,
            });
        } catch (exception) {
            setError(
                exception instanceof HttpError
                    ? exception.firstMessage()
                    : 'Could not save the prescription.',
            );
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Prescription for {productName}</DialogTitle>
                    <DialogDescription>
                        {dangerous
                            ? "Dangerous drug: needs the special prescription form, the doctor's S2 licence no. and the pharmacist."
                            : 'Choose the prescription being filled. Partial fills are allowed.'}
                    </DialogDescription>
                </DialogHeader>

                {!creating && (
                    <div className="space-y-2">
                        {lines.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No open prescriptions for this medicine.
                            </p>
                        )}
                        {lines.map((line) => (
                            <Button
                                key={line.id}
                                variant="outline"
                                className="h-auto w-full justify-between py-2 text-left"
                                disabled={line.balance < quantityBase}
                                onClick={() =>
                                    onPick({
                                        lineId: line.id,
                                        label: `${line.number} · ${line.patient_name}`,
                                    })
                                }
                            >
                                <span>
                                    <span className="block font-medium">
                                        {line.number} · {line.patient_name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {line.prescriber} ·{' '}
                                        {formatDate(line.prescribed_on)} ·{' '}
                                        {line.product}
                                    </span>
                                </span>
                                <span className="text-sm">
                                    {line.balance} left
                                </span>
                            </Button>
                        ))}
                        <Button
                            variant="secondary"
                            className="w-full"
                            onClick={() => setCreating(true)}
                        >
                            Record a new prescription
                        </Button>
                    </div>
                )}

                {creating && (
                    <div className="space-y-3">
                        <Field
                            label="Patient name"
                            htmlFor="rx-patient"
                            required
                        >
                            <Input
                                id="rx-patient"
                                autoFocus
                                value={form.patient_name}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        patient_name: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label="Patient address"
                            htmlFor="rx-address"
                            required={dangerous}
                        >
                            <Input
                                id="rx-address"
                                value={form.patient_address}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        patient_address: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        <PrescriberFields
                            idPrefix="rx"
                            value={prescriber}
                            onChange={setPrescriber}
                            needsS2={dangerous}
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Date" htmlFor="rx-date" required>
                                <Input
                                    id="rx-date"
                                    type="date"
                                    value={form.prescribed_on}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            prescribed_on: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                label="Quantity prescribed"
                                htmlFor="rx-qty"
                                required
                            >
                                <Input
                                    id="rx-qty"
                                    inputMode="numeric"
                                    value={form.quantity_prescribed_base}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            quantity_prescribed_base:
                                                event.target.value,
                                        })
                                    }
                                />
                            </Field>
                        </div>
                        {dangerous && (
                            <Field
                                label="Special prescription form serial no."
                                htmlFor="rx-serial"
                                required
                            >
                                <Input
                                    id="rx-serial"
                                    value={form.special_form_serial}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            special_form_serial:
                                                event.target.value,
                                        })
                                    }
                                />
                            </Field>
                        )}
                        {error && (
                            <p className="text-sm text-destructive">{error}</p>
                        )}
                        {missing.length > 0 && (
                            <p className="text-sm text-muted-foreground">
                                To save: {missing.join(', ')}.
                            </p>
                        )}
                        <DialogFooter>
                            <Button
                                variant="ghost"
                                onClick={() => setCreating(false)}
                            >
                                Back
                            </Button>
                            <Button
                                onClick={() => void save()}
                                disabled={missing.length > 0}
                            >
                                Save and use
                            </Button>
                        </DialogFooter>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
