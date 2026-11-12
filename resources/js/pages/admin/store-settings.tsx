import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Field } from '@/components/form/field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { edit, update } from '@/routes/admin/settings';

type SettingType =
    | 'string'
    | 'text'
    | 'bool'
    | 'int'
    | 'decimal'
    | 'int_list'
    | 'select';

type Definition = {
    key: string;
    group: string;
    label: string;
    type: SettingType;
    help: string | null;
    options: Record<string, string> | null;
};

type Props = {
    groups: Record<string, { label: string; description: string }>;
    definitions: Definition[];
    values: Record<string, unknown>;
};

type FormValue = string | boolean;

/** Turns stored values into what the inputs edit (lists become "90, 60, 30"). */
function toFormValues(
    definitions: Definition[],
    values: Record<string, unknown>,
): Record<string, FormValue> {
    return Object.fromEntries(
        definitions.map((definition) => {
            const value = values[definition.key];

            if (definition.type === 'bool') {
                return [definition.key, Boolean(value)];
            }

            if (definition.type === 'int_list') {
                return [
                    definition.key,
                    Array.isArray(value) ? value.join(', ') : '',
                ];
            }

            return [
                definition.key,
                typeof value === 'string' || typeof value === 'number'
                    ? String(value)
                    : '',
            ];
        }),
    );
}

/** { 'tax.vat_rate': '12' } becomes { tax: { vat_rate: '12' } }. */
function nest(
    flat: Record<string, FormValue>,
): Record<string, Record<string, FormValue>> {
    const nested: Record<string, Record<string, FormValue>> = {};

    for (const [key, value] of Object.entries(flat)) {
        const [group, name] = key.split('.', 2);
        nested[group] = { ...nested[group], [name]: value };
    }

    return nested;
}

export default function StoreSettings({ groups, definitions, values }: Props) {
    const groupKeys = Object.keys(groups);
    const [activeGroup, setActiveGroup] = useState(groupKeys[0]);
    const form = useForm<{ settings: Record<string, FormValue> }>({
        settings: toFormValues(definitions, values),
    });

    const errors = form.errors as Record<string, string | undefined>;
    const groupsWithErrors = new Set(
        definitions
            .filter((definition) =>
                Object.keys(errors).some((key) =>
                    key.startsWith(`settings.${definition.key}`),
                ),
            )
            .map((definition) => definition.group),
    );

    const setValue = (key: string, value: FormValue) =>
        form.setData('settings', { ...form.data.settings, [key]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // The server expects settings nested by group: settings[tax][vat_rate].
        form.transform((data) => ({ settings: nest(data.settings) }));
        form.put(update().url, { preserveScroll: true });
    };

    const renderInput = (definition: Definition) => {
        const id = `setting-${definition.key}`;
        const value = form.data.settings[definition.key];
        const error = errors[`settings.${definition.key}`];

        if (definition.type === 'bool') {
            return (
                <div key={definition.key} className="grid gap-1">
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id={id}
                            checked={value === true}
                            onCheckedChange={(checked) =>
                                setValue(definition.key, checked === true)
                            }
                        />
                        <label htmlFor={id} className="text-sm font-medium">
                            {definition.label}
                        </label>
                    </div>
                    {definition.help && (
                        <p className="pl-7 text-xs text-muted-foreground">
                            {definition.help}
                        </p>
                    )}
                    {error && (
                        <p className="pl-7 text-sm text-destructive">{error}</p>
                    )}
                </div>
            );
        }

        let input;

        if (definition.type === 'text') {
            input = (
                <Textarea
                    id={id}
                    value={String(value)}
                    onChange={(event) =>
                        setValue(definition.key, event.target.value)
                    }
                />
            );
        } else if (definition.type === 'select') {
            input = (
                <Select
                    value={String(value)}
                    onValueChange={(next) => setValue(definition.key, next)}
                >
                    <SelectTrigger id={id}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(definition.options ?? {}).map(
                            ([optionValue, optionLabel]) => (
                                <SelectItem
                                    key={optionValue}
                                    value={optionValue}
                                >
                                    {optionLabel}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>
            );
        } else {
            input = (
                <Input
                    id={id}
                    value={String(value)}
                    inputMode={
                        definition.type === 'int' ||
                        definition.type === 'decimal'
                            ? 'decimal'
                            : undefined
                    }
                    onChange={(event) =>
                        setValue(definition.key, event.target.value)
                    }
                />
            );
        }

        return (
            <Field
                key={definition.key}
                label={definition.label}
                htmlFor={id}
                help={definition.help}
                error={error}
            >
                {input}
            </Field>
        );
    };

    return (
        <>
            <Head title="Store settings" />

            <div className="px-4 py-6">
                <Heading
                    title="Store settings"
                    description="Tax, discount, stock and POS rules for the whole store"
                />

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-8 lg:flex-row lg:gap-12"
                >
                    <nav
                        className="flex gap-1 overflow-x-auto lg:w-56 lg:flex-col"
                        aria-label="Setting groups"
                    >
                        {groupKeys.map((key) => (
                            <Button
                                key={key}
                                type="button"
                                size="sm"
                                variant="ghost"
                                className={cn('justify-start', {
                                    'bg-muted': key === activeGroup,
                                    'text-destructive':
                                        groupsWithErrors.has(key),
                                })}
                                onClick={() => setActiveGroup(key)}
                            >
                                {groups[key].label}
                            </Button>
                        ))}
                    </nav>

                    <div className="max-w-xl flex-1 space-y-6">
                        <Heading
                            variant="small"
                            title={groups[activeGroup].label}
                            description={groups[activeGroup].description}
                        />

                        {definitions
                            .filter(
                                (definition) =>
                                    definition.group === activeGroup,
                            )
                            .map(renderInput)}

                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save settings
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

StoreSettings.layout = {
    breadcrumbs: [{ title: 'Store settings', href: edit() }],
};
