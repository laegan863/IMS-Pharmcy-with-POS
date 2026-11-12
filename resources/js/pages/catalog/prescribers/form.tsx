import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, store, update } from '@/routes/catalog/prescribers';

type Prescriber = {
    id: number;
    name: string;
    prc_license_number: string | null;
    ptr_number: string | null;
    s2_license_number: string | null;
    specialty: string | null;
    clinic_address: string | null;
    phone: string | null;
    is_active: boolean;
};

export default function PrescriberForm({
    prescriber,
}: {
    prescriber: Prescriber | null;
}) {
    const form = useForm({
        name: prescriber?.name ?? '',
        prc_license_number: prescriber?.prc_license_number ?? '',
        ptr_number: prescriber?.ptr_number ?? '',
        s2_license_number: prescriber?.s2_license_number ?? '',
        specialty: prescriber?.specialty ?? '',
        clinic_address: prescriber?.clinic_address ?? '',
        phone: prescriber?.phone ?? '',
        is_active: prescriber?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (prescriber) {
            form.put(update(prescriber.id).url);
        } else {
            form.post(store().url);
        }
    };

    const text = (
        id: Exclude<keyof typeof form.data, 'is_active'>,
        label: string,
        extra: Record<string, unknown> = {},
    ) => (
        <TextField
            id={id}
            label={label}
            value={form.data[id]}
            onChange={(value) => form.setData(id, value)}
            error={form.errors[id]}
            {...extra}
        />
    );

    const title = prescriber ? prescriber.name : 'New prescriber';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader title={title} />
                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    {text('name', 'Name', { required: true, autoFocus: true })}
                    <div className="grid gap-6 sm:grid-cols-3">
                        {text('prc_license_number', 'PRC licence no.')}
                        {text('ptr_number', 'PTR no.')}
                        {text('s2_license_number', 'S2 licence no.', {
                            help: 'Needed to prescribe dangerous drugs.',
                        })}
                    </div>
                    <div className="grid gap-6 sm:grid-cols-2">
                        {text('specialty', 'Specialty')}
                        {text('phone', 'Phone')}
                    </div>
                    {text('clinic_address', 'Clinic address')}
                    <CheckboxField
                        id="is_active"
                        label="Active"
                        checked={form.data.is_active}
                        onChange={(value) => form.setData('is_active', value)}
                    />
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save prescriber
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

PrescriberForm.layout = {
    breadcrumbs: [{ title: 'Prescribers', href: index() }],
};
