import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';

type CheckboxFieldProps = {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    help?: string;
    error?: string;
    disabled?: boolean;
};

/** A checkbox with its label to the right, plus hint and error. */
export function CheckboxField({
    id,
    label,
    checked,
    onChange,
    help,
    error,
    disabled,
}: CheckboxFieldProps) {
    return (
        <div className="grid gap-1">
            <div className="flex items-center gap-3">
                <Checkbox
                    id={id}
                    checked={checked}
                    disabled={disabled}
                    onCheckedChange={(value) => onChange(value === true)}
                />
                <label htmlFor={id} className="text-sm font-medium">
                    {label}
                </label>
            </div>
            {help && (
                <p className="pl-7 text-xs text-muted-foreground">{help}</p>
            )}
            <InputError message={error} className="pl-7" />
        </div>
    );
}
