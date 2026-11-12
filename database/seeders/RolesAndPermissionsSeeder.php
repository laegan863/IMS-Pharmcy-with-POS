<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role as DefaultRole;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs the permission list to the database and creates the default roles.
 * Safe to run on a live store after adding a permission:
 *  - permissions missing from the database are created, removed ones deleted;
 *  - the Owner role always gets every permission and stays locked;
 *  - other default roles are created with their starting permissions only
 *    if they do not exist yet, so changes made on the Roles page are kept;
 *  - roles created on the Roles page are left alone.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $names = array_map(fn (Permission $permission) => $permission->value, Permission::cases());

        foreach ($names as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        PermissionModel::query()->whereNotIn('name', $names)->delete();

        // Model events may be muted (WithoutModelEvents), so clear the cache by hand
        // before roles look the new permissions up.
        $registrar->forgetCachedPermissions();

        foreach (DefaultRole::cases() as $default) {
            $role = Role::query()->where('name', $default->value)->where('guard_name', 'web')->first();
            $isNew = $role === null;

            $role ??= new Role(['name' => $default->value, 'guard_name' => 'web']);

            if ($isNew || $role->label === null) {
                $role->label = $default->label();
                $role->requires_two_factor = $default->requiresTwoFactor();
            }

            $role->is_system = $default === DefaultRole::Owner;
            $role->save();

            if ($default === DefaultRole::Owner) {
                $role->syncPermissions($names);
            } elseif ($isNew) {
                $role->syncPermissions(array_map(fn (Permission $permission) => $permission->value, $default->permissions()));
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
