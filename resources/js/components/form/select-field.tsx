import { Field } from '@/components/form/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type Option = { value: string; label: string };

type SelectFieldProps = {
    id: string;
    label: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    error?: string;
    help?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
};

/** A labelled dropdown that shows its server validation error. */
export function SelectField({
    id,
    label,
    value,
    options,
    onChange,
    error,
    help,
    placeholder = 'Choose…',
    required,
    disabled,
    className,
}: SelectFieldProps) {
    return (
        <Field
            label={label}
            htmlFor={id}
            error={error}
            help={help}
            required={required}
            className={className}
        >
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger id={id} aria-invalid={error ? true : undefined}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}
