import { useEffect, useState } from 'react';
import { NativeSelect } from '@/components/form/native-select';
import { formatDate } from '@/lib/format';

export type BatchOption = {
    batch_id: number;
    lot_number: string;
    expires_on: string;
    status: string;
    quantity: number;
    unit_cost: string;
};

/**
 * Loads the batches of one product that this branch holds, from the given
 * JSON endpoint, and lets the user choose one.
 */
export function BatchPicker({
    url,
    value,
    onChange,
    disabled,
}: {
    url: string;
    value: number | null;
    onChange: (batch: BatchOption) => void;
    disabled?: boolean;
}) {
    const [batches, setBatches] = useState<BatchOption[]>([]);

    useEffect(() => {
        let cancelled = false;

        void fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((body: { data: BatchOption[] }) => {
                if (!cancelled) {
                    setBatches(body.data);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [url]);

    return (
        <NativeSelect
            aria-label="Batch"
            value={value ?? ''}
            disabled={disabled}
            onChange={(event) => {
                const batch = batches.find(
                    (option) => option.batch_id === Number(event.target.value),
                );

                if (batch) {
                    onChange(batch);
                }
            }}
        >
            <option value="" disabled>
                Choose batch…
            </option>
            {batches.map((batch) => (
                <option key={batch.batch_id} value={batch.batch_id}>
                    {batch.lot_number} · exp {formatDate(batch.expires_on)} ·{' '}
                    {batch.quantity} on hand
                    {batch.status !== 'active' ? ` · ${batch.status}` : ''}
                </option>
            ))}
        </NativeSelect>
    );
}
