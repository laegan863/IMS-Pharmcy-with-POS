import { Head, Link, router } from '@inertiajs/react';
import { Lock, Plus } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit, index } from '@/routes/admin/roles';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    is_system: boolean;
    requires_two_factor: boolean;
    users_count: number;
    permissions_count: number;
};

export default function RolesIndex({
    roles,
    totalPermissions,
}: {
    roles: RoleRow[];
    totalPermissions: number;
}) {
    return (
        <>
            <Head title="Roles" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Roles"
                    description="Each role decides which modules and actions its users can use. Owner always has full access."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New role
                            </Link>
                        </Button>
                    }
                />
                <div className="max-w-5xl rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Access</TableHead>
                                <TableHead className="text-right">
                                    Users
                                </TableHead>
                                <TableHead>Sign-in</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow
                                    key={role.id}
                                    className="cursor-pointer"
                                    onClick={() => router.visit(edit(role.id))}
                                >
                                    <TableCell className="whitespace-normal">
                                        <div className="flex items-center gap-2 font-medium">
                                            {role.label}
                                            {role.is_system && (
                                                <Lock
                                                    className="size-3.5 text-muted-foreground"
                                                    aria-label="Locked"
                                                />
                                            )}
                                        </div>
                                        {role.description && (
                                            <div className="text-xs text-muted-foreground">
                                                {role.description}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {role.is_system
                                            ? 'Everything'
                                            : `${role.permissions_count} of ${totalPermissions} permissions`}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {role.users_count}
                                    </TableCell>
                                    <TableCell>
                                        {role.requires_two_factor ? (
                                            <StatusBadge tone="info">
                                                Two-factor required
                                            </StatusBadge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Password
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
