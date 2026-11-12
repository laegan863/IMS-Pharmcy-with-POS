import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Tour } from '@/components/tour';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { shiftOpenTour } from '@/lib/tours';
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
                    <div data-tour="shift-terminal">
                        <SelectField
                            id="terminal_id"
                            label="Terminal"
                            value={form.data.terminal_id}
                            options={terminals}
                            onChange={(value) =>
                                form.setData('terminal_id', value)
                            }
                            error={form.errors.terminal_id}
                            required
                        />
                    </div>
                    <div data-tour="shift-opening-cash">
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
                    </div>
                    <Button
                        type="submit"
                        data-tour="shift-open-button"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Open shift
                    </Button>
                </form>
            </div>
            <Tour tourKey="shift-open" steps={shiftOpenTour} waitFor="main" />
        </>
    );
}

ShiftOpen.layout = {
    breadcrumbs: [{ title: 'Open shift', href: create() }],
};
