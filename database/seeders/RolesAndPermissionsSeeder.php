<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs the Permission and Role enums to the database. Safe to run again
 * after adding a permission: it creates what is missing and removes
 * permissions no longer in the enum.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = array_map(fn (Permission $permission) => $permission->value, Permission::cases());

        foreach ($names as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        PermissionModel::query()->whereNotIn('name', $names)->delete();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $permission) => $permission->value, $role->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
