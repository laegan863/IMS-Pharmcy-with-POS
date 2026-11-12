import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, store, update } from '@/routes/admin/users';

type EditableUser = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    branch_id: number | null;
    role: string | null;
    is_active: boolean;
    has_supervisor_pin: boolean;
    is_self: boolean;
};

type Props = {
    user: EditableUser | null;
    roles: Option[];
    branches: Option[];
};

export default function UserForm({ user, roles, branches }: Props) {
    const form = useForm({
        name: user?.name ?? '',
        username: user?.username ?? '',
        email: user?.email ?? '',
        branch_id: user?.branch_id ? String(user.branch_id) : '',
        role: user?.role ?? '',
        is_active: user?.is_active ?? true,
        password: '',
        password_confirmation: '',
        supervisor_pin: '',
        remove_supervisor_pin: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (user) {
            form.put(update(user.id).url);
        } else {
            form.post(store().url);
        }
    };

    const title = user ? `Edit ${user.name}` : 'New user';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader title={title} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <TextField
                        id="name"
                        label="Full name"
                        value={form.data.name}
                        onChange={(value) => form.setData('name', value)}
                        error={form.errors.name}
                        required
                        autoFocus
                    />
                    <div className="grid gap-6 sm:grid-cols-2">
                        <TextField
                            id="username"
                            label="Username"
                            value={form.data.username}
                            onChange={(value) =>
                                form.setData('username', value)
                            }
                            error={form.errors.username}
                            help="Used to sign in and to approve as supervisor."
                            autoCapitalize="none"
                            required
                        />
                        <TextField
                            id="email"
                            label="Email"
                            type="email"
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email}
                            required
                        />
                    </div>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <SelectField
                            id="role"
                            label="Role"
                            value={form.data.role}
                            options={roles}
                            onChange={(value) => form.setData('role', value)}
                            error={form.errors.role}
                            disabled={user?.is_self}
                            required
                        />
                        <SelectField
                            id="branch_id"
                            label="Home branch"
                            value={form.data.branch_id}
                            options={branches}
                            onChange={(value) =>
                                form.setData('branch_id', value)
                            }
                            error={form.errors.branch_id}
                            required
                        />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <TextField
                            id="password"
                            label={user ? 'New password' : 'Password'}
                            type="password"
                            value={form.data.password}
                            onChange={(value) =>
                                form.setData('password', value)
                            }
                            error={form.errors.password}
                            help={
                                user
                                    ? 'Leave blank to keep the current password.'
                                    : undefined
                            }
                            autoComplete="new-password"
                            required={!user}
                        />
                        <TextField
                            id="password_confirmation"
                            label="Confirm password"
                            type="password"
                            value={form.data.password_confirmation}
                            onChange={(value) =>
                                form.setData('password_confirmation', value)
                            }
                            autoComplete="new-password"
                            required={!user}
                        />
                    </div>

                    <TextField
                        id="supervisor_pin"
                        label="Supervisor PIN"
                        type="password"
                        inputMode="numeric"
                        value={form.data.supervisor_pin}
                        onChange={(value) =>
                            form.setData('supervisor_pin', value)
                        }
                        error={form.errors.supervisor_pin}
                        help={
                            user?.has_supervisor_pin
                                ? 'A PIN is set. Enter a new one to change it.'
                                : '4 to 8 digits. Needed to approve voids, refunds and overrides.'
                        }
                        autoComplete="off"
                    />
                    {user?.has_supervisor_pin && (
                        <CheckboxField
                            id="remove_supervisor_pin"
                            label="Remove supervisor PIN"
                            checked={form.data.remove_supervisor_pin}
                            onChange={(value) =>
                                form.setData('remove_supervisor_pin', value)
                            }
                        />
                    )}

                    <CheckboxField
                        id="is_active"
                        label="Active"
                        checked={form.data.is_active}
                        onChange={(value) => form.setData('is_active', value)}
                        help="Deactivated users cannot sign in. Users are never deleted."
                        error={form.errors.is_active}
                        disabled={user?.is_self}
                    />

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {user ? 'Save changes' : 'Create user'}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
