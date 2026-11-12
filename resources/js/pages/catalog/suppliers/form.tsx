import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, store, update } from '@/routes/catalog/suppliers';

type Supplier = {
    id: number;
    code: string;
    name: string;
    tin: string | null;
    address: string | null;
    fda_license_number: string | null;
    fda_license_expires_on: string | null;
    payment_terms_days: number;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
};

export default function SupplierForm({
    supplier,
}: {
    supplier: Supplier | null;
}) {
    const form = useForm({
        code: supplier?.code ?? '',
        name: supplier?.name ?? '',
        tin: supplier?.tin ?? '',
        address: supplier?.address ?? '',
        fda_license_number: supplier?.fda_license_number ?? '',
        fda_license_expires_on: supplier?.fda_license_expires_on ?? '',
        payment_terms_days: String(supplier?.payment_terms_days ?? 30),
        contact_person: supplier?.contact_person ?? '',
        phone: supplier?.phone ?? '',
        email: supplier?.email ?? '',
        is_active: supplier?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (supplier) {
            form.put(update(supplier.id).url);
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

    return (
        <>
            <Head title={supplier ? supplier.name : 'New supplier'} />
            <div className="px-4 py-6">
                <PageHeader title={supplier ? supplier.name : 'New supplier'} />
                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <div className="grid gap-6 sm:grid-cols-3">
                        {text('code', 'Code', { required: true })}
                        {text('name', 'Name', {
                            required: true,
                            fieldClassName: 'sm:col-span-2',
                        })}
                    </div>
                    <div className="grid gap-6 sm:grid-cols-2">
                        {text('tin', 'TIN', {
                            placeholder: '000-000-000-00000',
                        })}
                        {text('payment_terms_days', 'Payment terms (days)', {
                            inputMode: 'numeric',
                            required: true,
                            help: '0 for cash on delivery.',
                        })}
                    </div>
                    {text('address', 'Address')}
                    <div className="grid gap-6 sm:grid-cols-2">
                        {text(
                            'fda_license_number',
                            'FDA licence to operate no.',
                        )}
                        {text('fda_license_expires_on', 'Licence expires on', {
                            type: 'date',
                        })}
                    </div>
                    <div className="grid gap-6 sm:grid-cols-3">
                        {text('contact_person', 'Contact person')}
                        {text('phone', 'Phone')}
                        {text('email', 'Email', { type: 'email' })}
                    </div>
                    <CheckboxField
                        id="is_active"
                        label="Active"
                        checked={form.data.is_active}
                        onChange={(value) => form.setData('is_active', value)}
                    />
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save supplier
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

SupplierForm.layout = {
    breadcrumbs: [{ title: 'Suppliers', href: index() }],
};
