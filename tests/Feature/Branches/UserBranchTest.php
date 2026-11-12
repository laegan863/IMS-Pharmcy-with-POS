<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_belongs_to_a_home_branch()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->for($branch)->create();

        $this->assertTrue($user->branch->is($branch));
        $this->assertTrue($branch->users->contains($user));
    }

    public function test_new_users_are_active_by_default()
    {
        $user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@example.com',
            'password' => 'password',
        ]);

        $this->assertTrue($user->fresh()?->is_active);
    }

    public function test_usernames_are_unique()
    {
        User::factory()->create(['username' => 'cashier1']);

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => 'cashier1']);
    }

    public function test_the_supervisor_pin_is_stored_hashed_and_hidden()
    {
        $user = User::factory()->create();
        $user->supervisor_pin = '4321';
        $user->save();

        $stored = $user->fresh()?->supervisor_pin;

        $this->assertNotSame('4321', $stored);
        $this->assertTrue(Hash::check('4321', (string) $stored));
        $this->assertArrayNotHasKey('supervisor_pin', $user->toArray());
    }

    public function test_a_supervisor_pin_is_checked()
    {
        $user = User::factory()->withSupervisorPin('4321')->make();

        $this->assertTrue($user->checkSupervisorPin('4321'));
        $this->assertFalse($user->checkSupervisorPin('1234'));
    }

    public function test_a_user_without_a_pin_never_passes_the_check()
    {
        $user = User::factory()->make();

        $this->assertFalse($user->checkSupervisorPin(''));
        $this->assertFalse($user->checkSupervisorPin('1234'));
    }
}
