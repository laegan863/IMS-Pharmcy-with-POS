import { Head, Link, router, useForm } from '@inertiajs/react';
import { Lock, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { TextField } from '@/components/form/text-field';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, store, update } from '@/routes/admin/roles';

type Module = {
    name: string;
    permissions: { value: string; label: string; grantable: boolean }[];
};

type EditableRole = {
    id: number;
    label: string;
    description: string | null;
    requires_two_factor: boolean;
    is_system: boolean;
    permissions: string[];
    users_count: number;
    is_own_role: boolean;
};

export default function RoleForm({
    role,
    modules,
}: {
    role: EditableRole | null;
    modules: Module[];
}) {
    const locked = Boolean(role?.is_system || role?.is_own_role);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const form = useForm({
        label: role?.label ?? '',
        description: role?.description ?? '',
        requires_two_factor: role?.requires_two_factor ?? false,
        permissions: role?.is_system
            ? modules.flatMap((module) =>
                  module.permissions.map((p) => p.value),
              )
            : (role?.permissions ?? []),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const selected = new Set(form.data.permissions);

    const toggle = (values: string[], on: boolean) => {
        const next = new Set(form.data.permissions);
        values.forEach((value) => (on ? next.add(value) : next.delete(value)));
        form.setData('permissions', [...next]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (role) {
            form.put(update(role.id).url);
        } else {
            form.post(store().url);
        }
    };

    const title = role ? role.label : 'New role';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader
                    title={title}
                    description={
                        role
                            ? `${role.users_count} user(s) have this role.`
                            : 'Name the role, then tick what it may use.'
                    }
                    actions={
                        role &&
                        !locked && (
                            <Button
                                variant="outline"
                                disabled={role.users_count > 0}
                                title={
                                    role.users_count > 0
                                        ? 'Move its users to another role first'
                                        : undefined
                                }
                                onClick={() => setConfirmDelete(true)}
                            >
                                <Trash2 /> Delete role
                            </Button>
                        )
                    }
                />

                {role?.is_system && (
                    <Alert className="mb-6 max-w-4xl">
                        <Lock />
                        <AlertDescription>
                            The Owner role always has every permission so the
                            store can never be locked out. It cannot be changed.
                        </AlertDescription>
                    </Alert>
                )}
                {role?.is_own_role && !role.is_system && (
                    <Alert className="mb-6 max-w-4xl">
                        <Lock />
                        <AlertDescription>
                            This is your own role. Ask an owner to change it.
                        </AlertDescription>
                    </Alert>
                )}
                <InputError message={errors.role} className="mb-4" />

                <form onSubmit={submit} className="max-w-4xl space-y-8">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <TextField
                            id="label"
                            label="Role name"
                            value={form.data.label}
                            onChange={(value) => form.setData('label', value)}
                            error={errors.label}
                            disabled={locked}
                            placeholder="e.g. Pharmacy assistant"
                            required
                            autoFocus={!role}
                        />
                        <TextField
                            id="description"
                            label="Description"
                            value={form.data.description}
                            onChange={(value) =>
                                form.setData('description', value)
                            }
                            error={errors.description}
                            disabled={locked}
                        />
                    </div>
                    <CheckboxField
                        id="requires_two_factor"
                        label="Require two-factor authentication"
                        help="Recommended for roles that approve, see personal data or change prices."
                        checked={form.data.requires_two_factor}
                        disabled={locked}
                        onChange={(value) =>
                            form.setData('requires_two_factor', value)
                        }
                    />

                    <section className="space-y-4">
                        <div className="flex items-end justify-between">
                            <h2 className="font-medium">
                                Allowed modules and actions
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {selected.size} selected
                            </span>
                        </div>
                        <InputError message={errors.permissions} />
                        <div className="grid gap-4 md:grid-cols-2">
                            {modules.map((module) => {
                                const grantable = module.permissions.filter(
                                    (permission) => permission.grantable,
                                );
                                const checked = module.permissions.filter(
                                    (permission) =>
                                        selected.has(permission.value),
                                ).length;
                                const all =
                                    checked === module.permissions.length;
                                const moduleId = `module-${module.name.replace(/\W+/g, '-')}`;

                                return (
                                    <fieldset
                                        key={module.name}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="mb-3 flex items-center gap-3 border-b pb-3">
                                            <Checkbox
                                                id={moduleId}
                                                checked={
                                                    all
                                                        ? true
                                                        : checked > 0
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                disabled={
                                                    locked ||
                                                    grantable.length === 0
                                                }
                                                onCheckedChange={() =>
                                                    toggle(
                                                        grantable.map(
                                                            (p) => p.value,
                                                        ),
                                                        !all,
                                                    )
                                                }
                                            />
                                            <legend className="contents">
                                                <label
                                                    htmlFor={moduleId}
                                                    className="font-medium"
                                                >
                                                    {module.name}
                                                </label>
                                            </legend>
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {checked}/
                                                {module.permissions.length}
                                            </span>
                                        </div>
                                        <div className="space-y-2">
                                            {module.permissions.map(
                                                (permission) => (
                                                    <label
                                                        key={permission.value}
                                                        className="flex items-start gap-3 text-sm"
                                                    >
                                                        <Checkbox
                                                            className="mt-0.5"
                                                            checked={selected.has(
                                                                permission.value,
                                                            )}
                                                            disabled={
                                                                locked ||
                                                                !permission.grantable
                                                            }
                                                            onCheckedChange={(
                                                                value,
                                                            ) =>
                                                                toggle(
                                                                    [
                                                                        permission.value,
                                                                    ],
                                                                    value ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                        <span>
                                                            {permission.label}
                                                            {!permission.grantable && (
                                                                <span className="block text-xs text-muted-foreground">
                                                                    You do not
                                                                    have this
                                                                    yourself
                                                                </span>
                                                            )}
                                                        </span>
                                                    </label>
                                                ),
                                            )}
                                        </div>
                                    </fieldset>
                                );
                            })}
                        </div>
                    </section>

                    <div className="flex gap-2">
                        {!locked && (
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {role ? 'Save role' : 'Create role'}
                            </Button>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Back</Link>
                        </Button>
                    </div>
                </form>

                {role && (
                    <ConfirmDialog
                        open={confirmDelete}
                        onOpenChange={setConfirmDelete}
                        title={`Delete the ${role.label} role?`}
                        description="This cannot be undone."
                        confirmLabel="Delete role"
                        destructive
                        onConfirm={() =>
                            router.delete(destroy(role.id).url, {
                                onFinish: () => setConfirmDelete(false),
                            })
                        }
                    />
                )}
            </div>
        </>
    );
}

RoleForm.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
