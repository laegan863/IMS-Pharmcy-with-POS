import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Field } from '@/components/form/field';
import { TextField } from '@/components/form/text-field';
import { LineItemEditor } from '@/components/line-item-editor';
import type { LineColumn } from '@/components/line-item-editor';
import { PageHeader } from '@/components/page-header';
import { PrescriberFields } from '@/components/prescriber-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, store } from '@/routes/compliance/prescriptions';

type Line = {
    product_id: number;
    product_name: string;
    base_unit: string;
    classification: string;
    quantity_prescribed_base: number | string;
    directions: string;
};

export default function PrescriptionForm() {
    const form = useForm({
        patient_name: '',
        patient_address: '',
        prescriber_name: '',
        prescriber_prc_number: '',
        prescriber_s2_number: '',
        prescribed_on: new Date().toISOString().slice(0, 10),
        special_form_serial: '',
        notes: '',
        lines: [] as Line[],
        photo: null as File | null,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const hasDangerousDrug = form.data.lines.some(
        (line) => line.classification === 'dangerous_drug',
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, { forceFormData: true });
    };

    const columns: LineColumn<Line>[] = [
        {
            key: 'product_id',
            header: 'Medicine',
            className: 'min-w-56 whitespace-normal',
            cell: (line) => line.product_name,
        },
        {
            key: 'quantity_prescribed_base',
            header: 'Quantity prescribed',
            cell: (line, _i, set) => (
                <div className="flex items-center gap-2">
                    <Input
                        aria-label="Quantity prescribed"
                        inputMode="numeric"
                        className="w-20"
                        value={line.quantity_prescribed_base}
                        onChange={(event) =>
                            set({
                                quantity_prescribed_base: event.target.value,
                            })
                        }
                    />
                    <span className="text-sm text-muted-foreground">
                        {line.base_unit}
                    </span>
                </div>
            ),
        },
        {
            key: 'directions',
            header: 'Directions',
            cell: (line, _i, set) => (
                <Input
                    aria-label="Directions"
                    value={line.directions}
                    onChange={(event) =>
                        set({ directions: event.target.value })
                    }
                    placeholder="1 cap 3× a day for 7 days"
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Record prescription" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Record prescription"
                    description="Quantities are in base units (tablets, capsules, mL)."
                />
                <form onSubmit={submit} className="max-w-4xl space-y-6">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <TextField
                            id="patient_name"
                            label="Patient name"
                            value={form.data.patient_name}
                            onChange={(value) =>
                                form.setData('patient_name', value)
                            }
                            error={errors.patient_name}
                            required
                        />
                        <TextField
                            id="patient_address"
                            label="Patient address"
                            value={form.data.patient_address}
                            onChange={(value) =>
                                form.setData('patient_address', value)
                            }
                            error={errors.patient_address}
                        />
                    </div>
                    <div className="grid gap-6 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <PrescriberFields
                                idPrefix="rx"
                                value={{
                                    prescriber_name: form.data.prescriber_name,
                                    prescriber_prc_number:
                                        form.data.prescriber_prc_number,
                                    prescriber_s2_number:
                                        form.data.prescriber_s2_number,
                                }}
                                onChange={(next) =>
                                    form.setData((data) => ({
                                        ...data,
                                        ...next,
                                    }))
                                }
                                needsS2={hasDangerousDrug}
                                errors={errors}
                            />
                        </div>
                        <TextField
                            id="prescribed_on"
                            label="Prescription date"
                            type="date"
                            value={form.data.prescribed_on}
                            onChange={(value) =>
                                form.setData('prescribed_on', value)
                            }
                            error={errors.prescribed_on}
                            required
                        />
                    </div>
                    {hasDangerousDrug && (
                        <TextField
                            id="special_form_serial"
                            label="Special prescription form serial no. (S2)"
                            value={form.data.special_form_serial}
                            onChange={(value) =>
                                form.setData('special_form_serial', value)
                            }
                            error={errors.special_form_serial}
                            required
                        />
                    )}

                    <LineItemEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        columns={columns}
                        errors={errors}
                        pickerPlaceholder="Search the prescribed medicine…"
                        makeLine={(product) => ({
                            product_id: product.id,
                            product_name: product.name,
                            base_unit:
                                product.units.find((unit) => unit.is_base)
                                    ?.name ?? 'units',
                            classification: product.classification,
                            quantity_prescribed_base: '',
                            directions: '',
                        })}
                    />

                    <Field
                        label="Photo of the prescription"
                        htmlFor="photo"
                        error={errors.photo}
                        help="Optional. Kept privately; opening it is logged."
                    >
                        <Input
                            id="photo"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            onChange={(event) =>
                                form.setData(
                                    'photo',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </Field>
                    <Field label="Notes" htmlFor="notes" error={errors.notes}>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                    </Field>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save prescription
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Back</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

PrescriptionForm.layout = {
    breadcrumbs: [{ title: 'Prescriptions', href: index() }],
};
