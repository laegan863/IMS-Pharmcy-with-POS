import type { ComponentProps } from 'react';
import { Field } from '@/components/form/field';
import { Input } from '@/components/ui/input';

type TextFieldProps = Omit<ComponentProps<typeof Input>, 'onChange'> & {
    id: string;
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    error?: string;
    help?: string;
    fieldClassName?: string;
};

/** A labelled text input that shows its server validation error. */
export function TextField({
    id,
    label,
    value,
    onChange,
    error,
    help,
    required,
    fieldClassName,
    ...inputProps
}: TextFieldProps) {
    return (
        <Field
            label={label}
            htmlFor={id}
            error={error}
            help={help}
            required={required}
            className={fieldClassName}
        >
            <Input
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                aria-invalid={error ? true : undefined}
                {...inputProps}
            />
        </Field>
    );
}
