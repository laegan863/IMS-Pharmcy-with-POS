<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role as DefaultRole;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->withTwoFactor()->create()->assignRole(DefaultRole::Owner);
    }

    public function test_the_owner_creates_a_role_and_assigns_it()
    {
        $this->actingAs($this->owner)->post(route('admin.roles.store'), [
            'label' => 'Pharmacy assistant',
            'description' => 'Sells and records prescriptions',
            'requires_two_factor' => false,
            'permissions' => ['pos.sell', 'products.view', 'prescriptions.manage'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('label', 'Pharmacy assistant')->sole();
        $this->assertSame('pharmacy-assistant', $role->name);
        $this->assertDatabaseHas('activity_log', ['event' => 'role-created']);

        $this->actingAs($this->owner)->post(route('admin.users.store'), [
            'name' => 'Ana Assistant',
            'username' => 'ana',
            'email' => 'ana@example.com',
            'branch_id' => Branch::factory()->create()->id,
            'role' => 'pharmacy-assistant',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasNoErrors();

        $ana = User::query()->where('username', 'ana')->sole();
        $this->assertTrue($ana->can('pos.sell'));
        $this->assertFalse($ana->can('pos.void'));

        // The new role's users reach exactly the modules ticked for it.
        $this->actingAs($ana)->get(route('catalog.products.index'))->assertOk();
        $this->actingAs($ana)->get(route('compliance.prescriptions.index'))->assertOk();
        $this->actingAs($ana)->get(route('purchasing.orders.index'))->assertForbidden();
        $this->actingAs($ana)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_editing_a_role_changes_its_users_access()
    {
        $cashierRole = Role::findByName(DefaultRole::Cashier->value);
        $cashier = User::factory()->create()->assignRole($cashierRole);
        $this->assertFalse($cashier->can('stock.count'));

        $this->actingAs($this->owner)->put(route('admin.roles.update', $cashierRole), [
            'label' => 'Cashier',
            'requires_two_factor' => false,
            'permissions' => ['pos.sell', 'products.view', 'stock.view', 'stock.count'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($cashier->fresh()?->can('stock.count'));
        $this->assertDatabaseHas('activity_log', ['event' => 'role-updated']);
    }

    public function test_the_owner_role_is_locked()
    {
        $ownerRole = Role::findByName(DefaultRole::Owner->value);

        $this->actingAs($this->owner)
            ->put(route('admin.roles.update', $ownerRole), ['label' => 'Owner', 'permissions' => ['pos.sell']])
            ->assertSessionHasErrors('role');
        $this->actingAs($this->owner)->delete(route('admin.roles.destroy', $ownerRole))->assertSessionHasErrors('role');

        $this->assertSame(count(Permission::cases()), $ownerRole->permissions()->count());
    }

    public function test_a_role_with_users_cannot_be_deleted_but_an_empty_one_can()
    {
        $role = Role::query()->create(['name' => 'temp', 'guard_name' => 'web', 'label' => 'Temp']);
        $user = User::factory()->create()->assignRole($role);

        $this->actingAs($this->owner)->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');

        $user->syncRoles([DefaultRole::Cashier->value]);
        $this->actingAs($this->owner)->delete(route('admin.roles.destroy', $role))->assertSessionHasNoErrors();
        $this->assertNull(Role::query()->find($role->id));
    }

    public function test_a_non_owner_cannot_grant_more_than_they_have_or_edit_their_own_role()
    {
        $managerRole = Role::query()->create(['name' => 'manager', 'guard_name' => 'web', 'label' => 'Manager']);
        $managerRole->syncPermissions(['roles.manage', 'users.manage', 'pos.sell', 'pos.void']);
        $manager = User::factory()->create()->assignRole($managerRole);

        $this->actingAs($manager)->post(route('admin.roles.store'), [
            'label' => 'Shift lead',
            'permissions' => ['pos.sell', 'pos.void'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)->post(route('admin.roles.store'), [
            'label' => 'Sneaky',
            'permissions' => ['pos.sell', 'reports.profit'],
        ])->assertSessionHasErrors('permissions');

        $this->actingAs($manager)->put(route('admin.roles.update', $managerRole), [
            'label' => 'Manager',
            'permissions' => ['roles.manage', 'users.manage', 'pos.sell', 'pos.void'],
        ])->assertSessionHasErrors('role');

        // Nor can they make anyone an owner.
        $this->actingAs($manager)->post(route('admin.users.store'), [
            'name' => 'New Owner', 'username' => 'newowner', 'email' => 'no@example.com',
            'branch_id' => $manager->branch_id, 'role' => 'owner',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('role');

        $this->actingAs($manager)
            ->get(route('admin.roles.create'))
            ->assertInertia(fn (Assert $page) => $page->where('modules', fn ($modules) => collect($modules)
                ->flatMap(fn ($module) => $module['permissions'])
                ->firstWhere('value', 'reports.profit')['grantable'] === false));
    }

    public function test_two_factor_follows_the_role_setting()
    {
        $role = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web', 'label' => 'Auditor', 'requires_two_factor' => true]);
        $role->syncPermissions(['audit.view']);
        $auditor = User::factory()->create()->assignRole($role);

        $this->actingAs($auditor)->get(route('dashboard'))->assertRedirect(route('security.edit'));
    }

    public function test_the_seeder_keeps_changes_made_on_the_roles_page()
    {
        $cashierRole = Role::findByName(DefaultRole::Cashier->value);
        $cashierRole->syncPermissions(['pos.sell']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(['pos.sell'], $cashierRole->fresh()?->permissions->pluck('name')->all());
        $this->assertSame(count(Permission::cases()), Role::findByName('owner')->permissions()->count());
    }

    public function test_only_role_managers_reach_the_roles_page()
    {
        $pharmacist = User::factory()->withTwoFactor()->create()->assignRole(DefaultRole::Pharmacist);

        $this->actingAs($pharmacist)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($this->owner)
            ->get(route('admin.roles.index'))
            ->assertInertia(fn (Assert $page) => $page->has('roles', 4)->where('roles.0.is_system', true));
    }
}
