<?php

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_the_owner_has_every_permission()
    {
        $owner = User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($owner->can($permission->value), $permission->value);
        }
    }

    public function test_a_cashier_can_sell_but_not_void_or_manage_users()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->assertTrue($cashier->can(Permission::PosSell->value));
        $this->assertFalse($cashier->can(Permission::PosVoid->value));
        $this->assertFalse($cashier->can(Permission::UsersManage->value));
    }

    public function test_the_seeder_can_run_twice()
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(Permission::cases()), \Spatie\Permission\Models\Permission::count());
    }

    public function test_permissions_are_shared_with_the_front_end()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.role', 'cashier')
                ->where('auth.branch.id', $cashier->branch_id)
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('pos.sell')
                    && ! collect($permissions)->contains('users.manage')));
    }

    public function test_owners_without_two_factor_are_sent_to_the_security_page()
    {
        $owner = User::factory()->create()->assignRole(Role::Owner);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('security.edit'));
    }

    public function test_owners_with_two_factor_reach_the_dashboard()
    {
        $owner = User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }

    public function test_cashiers_do_not_need_two_factor()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)->get(route('dashboard'))->assertOk();
    }

    public function test_public_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
    }
}
