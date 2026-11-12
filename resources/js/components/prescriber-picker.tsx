import { UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { http, HttpError } from '@/lib/http';
import { cn } from '@/lib/utils';
import { prescribers as prescriberLookup } from '@/routes/pos/rx';
import { store as storePrescriber } from '@/routes/pos/rx/prescribers';

export type PrescriberOption = {
    id: number;
    name: string;
    prc_license_number: string | null;
    s2_license_number: string | null;
};

/**
 * Search prescribers by name or PRC number and pick one. When nobody
 * matches, the doctor can be added on the spot with a name and PRC number
 * (the rest is completed later on the Prescribers page).
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
    const [searched, setSearched] = useState('');
    const [adding, setAdding] = useState(false);
    const [prc, setPrc] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const trimmed = term.trim();

    useEffect(() => {
        if (trimmed.length < 2) {
            setResults([]);
            setSearched('');

            return;
        }

        const timer = window.setTimeout(async () => {
            const body = await http.get<{ data: PrescriberOption[] }>(
                prescriberLookup({ query: { q: trimmed } }).url,
            );
            setResults(body.data);
            setSearched(trimmed);
        }, 200);

        return () => window.clearTimeout(timer);
    }, [trimmed]);

    const pick = (prescriber: PrescriberOption) => {
        onChange(prescriber);
        setTerm('');
        setResults([]);
        setSearched('');
        setAdding(false);
        setPrc('');
        setError(null);
    };

    const add = async () => {
        setSaving(true);
        setError(null);

        try {
            const body = await http.post<{ data: PrescriberOption }>(
                storePrescriber().url,
                { name: trimmed, prc_license_number: prc.trim() || null },
            );
            pick(body.data);
        } catch (exception) {
            setError(
                exception instanceof HttpError
                    ? exception.firstMessage()
                    : 'Could not add the prescriber.',
            );
        } finally {
            setSaving(false);
        }
    };

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

    const noMatch =
        searched !== '' && searched === trimmed && results.length === 0;

    return (
        <div className="relative space-y-2">
            <Input
                id={id}
                value={term}
                onChange={(event) => {
                    setTerm(event.target.value);
                    setAdding(false);
                    setError(null);
                }}
                placeholder="Type the doctor's name or PRC no., then pick from the list"
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
                                onClick={() => pick(prescriber)}
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

            {noMatch && !adding && (
                <div className="flex items-center justify-between gap-2 rounded-md border border-dashed px-3 py-2 text-sm">
                    <span className="text-muted-foreground">
                        No prescriber named “{trimmed}” on file.
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setAdding(true)}
                    >
                        <UserPlus /> Add as new prescriber
                    </Button>
                </div>
            )}

            {noMatch && adding && (
                <div className="space-y-2 rounded-md border px-3 py-3 text-sm">
                    <div className="font-medium">Add {trimmed}</div>
                    <Input
                        aria-label="PRC licence number"
                        placeholder="PRC licence no. (from the prescription)"
                        value={prc}
                        autoFocus
                        onChange={(event) => setPrc(event.target.value)}
                    />
                    <InputError message={error ?? undefined} />
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            disabled={saving}
                            onClick={() => void add()}
                        >
                            Add and use
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setAdding(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
