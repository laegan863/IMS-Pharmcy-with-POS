import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Field } from '@/components/form/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

export type Approval = {
    approver: string;
    approver_pin: string;
};

type SupervisorApprovalDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    processing?: boolean;
    /** Server error for the PIN, from the request that used the approval. */
    error?: string;
    /** Called with the supervisor's username and PIN. The server checks them. */
    onApprove: (approval: Approval) => void;
    children?: ReactNode;
};

/**
 * Collects a supervisor's username and PIN for a void, refund, price
 * override or adjustment. The PIN is sent with the action request and checked
 * on the server by App\Support\SupervisorApproval; nothing is checked here.
 */
export function SupervisorApprovalDialog({
    open,
    onOpenChange,
    title,
    description,
    processing = false,
    error,
    onApprove,
    children,
}: SupervisorApprovalDialogProps) {
    const [approver, setApprover] = useState('');
    const [pin, setPin] = useState('');

    useEffect(() => {
        if (!open) {
            setPin('');
        }
    }, [open]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onApprove({ approver, approver_pin: pin });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            {description ??
                                'A supervisor must approve this action.'}
                        </DialogDescription>
                    </DialogHeader>

                    {children}

                    <Field label="Supervisor username" htmlFor="approver">
                        <Input
                            id="approver"
                            value={approver}
                            onChange={(event) =>
                                setApprover(event.target.value)
                            }
                            autoComplete="off"
                            autoFocus
                            required
                        />
                    </Field>
                    <Field label="PIN" htmlFor="approver_pin" error={error}>
                        <Input
                            id="approver_pin"
                            type="password"
                            inputMode="numeric"
                            value={pin}
                            onChange={(event) => setPin(event.target.value)}
                            autoComplete="off"
                            required
                        />
                    </Field>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Approve
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
