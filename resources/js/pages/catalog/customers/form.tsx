import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, store, update } from '@/routes/catalog/customers';

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    birthdate: string | null;
    senior_citizen_id_masked: string | null;
    pwd_id_masked: string | null;
    is_active: boolean;
};

export default function CustomerForm({
    customer,
}: {
    customer: Customer | null;
}) {
    const form = useForm({
        name: customer?.name ?? '',
        phone: customer?.phone ?? '',
        address: customer?.address ?? '',
        birthdate: customer?.birthdate ?? '',
        senior_citizen_id: '',
        pwd_id: '',
        clear_senior_citizen_id: false,
        clear_pwd_id: false,
        is_active: customer?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (customer) {
            form.put(update(customer.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <>
            <Head title={customer ? customer.name : 'New customer'} />
            <div className="px-4 py-6">
                <PageHeader title={customer ? customer.name : 'New customer'} />
                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <TextField
                        id="name"
                        label="Full name"
                        value={form.data.name}
                        onChange={(value) => form.setData('name', value)}
                        error={form.errors.name}
                        required
                        autoFocus
                    />
                    <div className="grid gap-6 sm:grid-cols-2">
                        <TextField
                            id="phone"
                            label="Phone"
                            value={form.data.phone}
                            onChange={(value) => form.setData('phone', value)}
                            error={form.errors.phone}
                        />
                        <TextField
                            id="birthdate"
                            label="Birthdate"
                            type="date"
                            value={form.data.birthdate}
                            onChange={(value) =>
                                form.setData('birthdate', value)
                            }
                            error={form.errors.birthdate}
                        />
                    </div>
                    <TextField
                        id="address"
                        label="Address"
                        value={form.data.address}
                        onChange={(value) => form.setData('address', value)}
                        error={form.errors.address}
                    />
                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="space-y-2">
                            <TextField
                                id="senior_citizen_id"
                                label="Senior citizen ID no."
                                value={form.data.senior_citizen_id}
                                onChange={(value) =>
                                    form.setData('senior_citizen_id', value)
                                }
                                error={form.errors.senior_citizen_id}
                                help={
                                    customer?.senior_citizen_id_masked
                                        ? `On file: ${customer.senior_citizen_id_masked}. Leave blank to keep.`
                                        : undefined
                                }
                                autoComplete="off"
                            />
                            {customer?.senior_citizen_id_masked && (
                                <CheckboxField
                                    id="clear_senior_citizen_id"
                                    label="Remove senior citizen ID"
                                    checked={form.data.clear_senior_citizen_id}
                                    onChange={(value) =>
                                        form.setData(
                                            'clear_senior_citizen_id',
                                            value,
                                        )
                                    }
                                />
                            )}
                        </div>
                        <div className="space-y-2">
                            <TextField
                                id="pwd_id"
                                label="PWD ID no."
                                value={form.data.pwd_id}
                                onChange={(value) =>
                                    form.setData('pwd_id', value)
                                }
                                error={form.errors.pwd_id}
                                help={
                                    customer?.pwd_id_masked
                                        ? `On file: ${customer.pwd_id_masked}. Leave blank to keep.`
                                        : undefined
                                }
                                autoComplete="off"
                            />
                            {customer?.pwd_id_masked && (
                                <CheckboxField
                                    id="clear_pwd_id"
                                    label="Remove PWD ID"
                                    checked={form.data.clear_pwd_id}
                                    onChange={(value) =>
                                        form.setData('clear_pwd_id', value)
                                    }
                                />
                            )}
                        </div>
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
                            Save customer
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

CustomerForm.layout = {
    breadcrumbs: [{ title: 'Customers', href: index() }],
};
