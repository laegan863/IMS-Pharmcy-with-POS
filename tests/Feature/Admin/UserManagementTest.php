<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use App\Support\SupervisorApproval;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);
    }

    public function test_the_owner_sees_the_user_list_with_search()
    {
        User::factory()->create(['name' => 'Maria Santos'])->assignRole(Role::Cashier);
        User::factory()->create(['name' => 'Jose Cruz'])->assignRole(Role::Cashier);

        $this->actingAs($this->owner)
            ->get(route('admin.users.index', ['search' => 'Maria']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.name', 'Maria Santos')
                ->where('state.search', 'Maria'));
    }

    public function test_sorting_ignores_unknown_columns()
    {
        $this->actingAs($this->owner)
            ->get(route('admin.users.index', ['sort' => 'password', 'direction' => 'desc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('state.sort', 'name'));
    }

    public function test_the_owner_creates_a_cashier()
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->owner)->post(route('admin.users.store'), [
            'name' => 'New Cashier',
            'username' => 'NewCashier',
            'email' => 'cashier2@example.com',
            'branch_id' => $branch->id,
            'role' => 'cashier',
            'is_active' => true,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'newcashier')->firstOrFail();
        $this->assertTrue($user->hasRole('cashier'));
        $this->assertTrue($user->branch?->is($branch));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_editing_keeps_the_password_when_left_blank_and_sets_a_pin()
    {
        $user = User::factory()->create()->assignRole(Role::Pharmacist);
        $oldPassword = $user->password;

        $this->actingAs($this->owner)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'branch_id' => $user->branch_id,
            'role' => 'pharmacist',
            'is_active' => true,
            'password' => '',
            'supervisor_pin' => '2468',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame($oldPassword, $user->password);
        $this->assertTrue($user->checkSupervisorPin('2468'));
    }

    public function test_owners_cannot_deactivate_themselves_or_change_their_role()
    {
        $this->actingAs($this->owner)->put(route('admin.users.update', $this->owner), [
            'name' => $this->owner->name,
            'username' => $this->owner->username,
            'email' => $this->owner->email,
            'branch_id' => $this->owner->branch_id,
            'role' => 'cashier',
            'is_active' => false,
        ])->assertSessionHasErrors(['is_active', 'role']);
    }

    public function test_a_pharmacist_cannot_manage_users()
    {
        $pharmacist = User::factory()->withTwoFactor()->create()->assignRole(Role::Pharmacist);

        $this->actingAs($pharmacist)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($pharmacist)->get(route('admin.audit-log.index'))->assertForbidden();
        $this->actingAs($pharmacist)->get(route('admin.branches.index'))->assertForbidden();
    }

    public function test_the_audit_log_lists_entries()
    {
        $this->actingAs($this->owner)
            ->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/audit-log')->has('rows.data'));
    }

    public function test_supervisor_approval_accepts_the_right_pin_and_permission()
    {
        $pharmacist = User::factory()->create(['username' => 'pharma', 'supervisor_pin' => Hash::make('1357')])
            ->assignRole(Role::Pharmacist);

        $approver = SupervisorApproval::verify('Pharma', '1357', Permission::PosVoid);

        $this->assertTrue($approver->is($pharmacist));
    }

    public function test_supervisor_approval_rejects_a_wrong_pin_or_missing_permission()
    {
        User::factory()->create(['username' => 'pharma', 'supervisor_pin' => Hash::make('1357')])->assignRole(Role::Pharmacist);
        User::factory()->create(['username' => 'cash', 'supervisor_pin' => Hash::make('1357')])->assignRole(Role::Cashier);

        foreach ([['pharma', '0000'], ['cash', '1357'], ['nobody', '1357']] as [$login, $pin]) {
            try {
                SupervisorApproval::verify($login, $pin, Permission::PosVoid);
                $this->fail("Approval by {$login} should fail.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('approver_pin', $exception->errors());
            }
        }
    }
}
