import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { create, store } from '@/routes/pos/shifts';

export default function ShiftOpen({ terminals }: { terminals: Option[] }) {
    const form = useForm({
        terminal_id: terminals[0]?.value ?? '',
        opening_cash: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url);
    };

    return (
        <>
            <Head title="Open shift" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Open a shift"
                    description="Count the cash float in the drawer before the first sale."
                />
                <form onSubmit={submit} className="max-w-md space-y-6">
                    <SelectField
                        id="terminal_id"
                        label="Terminal"
                        value={form.data.terminal_id}
                        options={terminals}
                        onChange={(value) => form.setData('terminal_id', value)}
                        error={form.errors.terminal_id}
                        required
                    />
                    <TextField
                        id="opening_cash"
                        label="Opening cash (₱)"
                        inputMode="decimal"
                        value={form.data.opening_cash}
                        onChange={(value) =>
                            form.setData('opening_cash', value)
                        }
                        error={form.errors.opening_cash}
                        autoFocus
                        required
                    />
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Open shift
                    </Button>
                </form>
            </div>
        </>
    );
}

ShiftOpen.layout = {
    breadcrumbs: [{ title: 'Open shift', href: create() }],
};
