import { useEffect, useState } from 'react';
import { Field } from '@/components/form/field';
import { Input } from '@/components/ui/input';
import { http } from '@/lib/http';
import { prescribers as prescriberLookup } from '@/routes/pos/rx';

export type PrescriberDetails = {
    prescriber_name: string;
    prescriber_prc_number: string;
    prescriber_s2_number: string;
};

type Suggestion = {
    id: number;
    name: string;
    prc_license_number: string | null;
    s2_license_number: string | null;
};

export const emptyPrescriber: PrescriberDetails = {
    prescriber_name: '',
    prescriber_prc_number: '',
    prescriber_s2_number: '',
};

/**
 * The prescriber as written on the prescription: free text, so any doctor
 * can be entered. Doctors kept on the Prescribers list show as suggestions
 * while typing; picking one fills in their PRC and S2 numbers.
 */
export function PrescriberFields({
    idPrefix,
    value,
    onChange,
    needsS2,
    errors = {},
}: {
    idPrefix: string;
    value: PrescriberDetails;
    onChange: (next: PrescriberDetails) => void;
    /** Dangerous drug on the prescription: the S2 licence number is required. */
    needsS2: boolean;
    errors?: Partial<Record<keyof PrescriberDetails, string>>;
}) {
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const [focused, setFocused] = useState(false);
    const term = value.prescriber_name.trim();

    useEffect(() => {
        if (term.length < 2) {
            setSuggestions([]);

            return;
        }

        const timer = window.setTimeout(async () => {
            const body = await http.get<{ data: Suggestion[] }>(
                prescriberLookup({ query: { q: term } }).url,
            );
            // Hide the list once the name typed is exactly a suggestion.
            setSuggestions(
                body.data.filter(
                    (suggestion) =>
                        suggestion.name.toLowerCase() !== term.toLowerCase(),
                ),
            );
        }, 200);

        return () => window.clearTimeout(timer);
    }, [term]);

    const set = (patch: Partial<PrescriberDetails>) =>
        onChange({ ...value, ...patch });

    const pick = (suggestion: Suggestion) => {
        onChange({
            prescriber_name: suggestion.name,
            prescriber_prc_number: suggestion.prc_license_number ?? '',
            prescriber_s2_number: suggestion.s2_license_number ?? '',
        });
        setSuggestions([]);
    };

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <Field
                label="Prescriber (doctor's name)"
                htmlFor={`${idPrefix}-prescriber`}
                error={errors.prescriber_name}
                required
                className="sm:col-span-2"
            >
                <div className="relative">
                    <Input
                        id={`${idPrefix}-prescriber`}
                        value={value.prescriber_name}
                        onChange={(event) =>
                            set({ prescriber_name: event.target.value })
                        }
                        onFocus={() => setFocused(true)}
                        // Delay so a click on a suggestion lands first.
                        onBlur={() =>
                            window.setTimeout(() => setFocused(false), 150)
                        }
                        placeholder="As written on the prescription, e.g. Dr. Liza Ramos"
                        autoComplete="off"
                        aria-invalid={
                            Boolean(errors.prescriber_name) || undefined
                        }
                    />
                    {focused && suggestions.length > 0 && (
                        <ul className="absolute z-50 mt-1 w-full rounded-md border bg-popover p-1 text-sm shadow-md">
                            {suggestions.map((suggestion) => (
                                <li key={suggestion.id}>
                                    <button
                                        type="button"
                                        className="w-full rounded-sm px-2 py-1.5 text-left hover:bg-accent"
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                        onClick={() => pick(suggestion)}
                                    >
                                        {suggestion.name}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            PRC{' '}
                                            {suggestion.prc_license_number ??
                                                '—'}
                                            {suggestion.s2_license_number &&
                                                ' · S2'}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </Field>
            <Field
                label="PRC licence no."
                htmlFor={`${idPrefix}-prc`}
                error={errors.prescriber_prc_number}
            >
                <Input
                    id={`${idPrefix}-prc`}
                    value={value.prescriber_prc_number}
                    onChange={(event) =>
                        set({ prescriber_prc_number: event.target.value })
                    }
                    placeholder="Optional"
                />
            </Field>
            {needsS2 && (
                <Field
                    label="S2 licence no."
                    htmlFor={`${idPrefix}-s2`}
                    error={errors.prescriber_s2_number}
                    required
                >
                    <Input
                        id={`${idPrefix}-s2`}
                        value={value.prescriber_s2_number}
                        onChange={(event) =>
                            set({ prescriber_s2_number: event.target.value })
                        }
                    />
                </Field>
            )}
        </div>
    );
}
