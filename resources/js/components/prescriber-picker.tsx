import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { prescribers as prescriberLookup } from '@/routes/pos/rx';

export type PrescriberOption = {
    id: number;
    name: string;
    prc_license_number: string | null;
    s2_license_number: string | null;
};

/**
 * Search prescribers by name or PRC number and pick one.
 */
export function PrescriberPicker({
    id,
    value,
    onChange,
    invalid,
}: {
    id: string;
    value: PrescriberOption | null;
    onChange: (prescriber: PrescriberOption | null) => void;
    invalid?: boolean;
}) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<PrescriberOption[]>([]);

    useEffect(() => {
        if (term.trim().length < 2) {
            setResults([]);

            return;
        }

        const timer = window.setTimeout(async () => {
            const response = await fetch(
                prescriberLookup({ query: { q: term.trim() } }).url,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );
            const body = (await response.json()) as {
                data: PrescriberOption[];
            };
            setResults(body.data);
        }, 200);

        return () => window.clearTimeout(timer);
    }, [term]);

    if (value) {
        return (
            <div className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                <span>
                    {value.name}
                    <span className="ml-2 text-xs text-muted-foreground">
                        PRC {value.prc_license_number ?? '—'}
                        {value.s2_license_number &&
                            ` · S2 ${value.s2_license_number}`}
                    </span>
                </span>
                <button
                    type="button"
                    className="text-xs underline"
                    onClick={() => onChange(null)}
                >
                    Change
                </button>
            </div>
        );
    }

    return (
        <div className="relative">
            <Input
                id={id}
                value={term}
                onChange={(event) => setTerm(event.target.value)}
                placeholder="Search name or PRC no."
                autoComplete="off"
                aria-invalid={invalid || undefined}
            />
            {results.length > 0 && (
                <ul className="absolute z-50 mt-1 w-full rounded-md border bg-popover p-1 text-sm shadow-md">
                    {results.map((prescriber) => (
                        <li key={prescriber.id}>
                            <button
                                type="button"
                                className={cn(
                                    'w-full rounded-sm px-2 py-1.5 text-left hover:bg-accent',
                                )}
                                onClick={() => {
                                    onChange(prescriber);
                                    setTerm('');
                                    setResults([]);
                                }}
                            >
                                {prescriber.name}
                                <span className="ml-2 text-xs text-muted-foreground">
                                    PRC {prescriber.prc_license_number ?? '—'}
                                    {prescriber.s2_license_number && ' · S2'}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
