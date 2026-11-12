<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_sees_only_their_own_branch_records()
    {
        [$main, $other] = Branch::factory()->count(2)->create();
        $own = Terminal::factory()->for($main)->create();
        Terminal::factory()->for($other)->create();

        $this->actingAs(User::factory()->for($main)->create());

        $this->assertSame([$own->id], Terminal::pluck('id')->all());
    }

    public function test_a_user_cannot_load_another_branch_record_by_id()
    {
        [$main, $other] = Branch::factory()->count(2)->create();
        $foreign = Terminal::factory()->for($other)->create();

        $this->actingAs(User::factory()->for($main)->create());

        $this->assertNull(Terminal::find($foreign->id));
    }

    public function test_a_user_without_a_branch_sees_no_records()
    {
        Terminal::factory()->count(2)->create();

        $this->actingAs(User::factory()->withoutBranch()->create());

        $this->assertSame(0, Terminal::count());
    }

    public function test_queries_without_a_signed_in_user_are_not_scoped()
    {
        Terminal::factory()->count(3)->create();

        $this->assertSame(3, Terminal::count());
    }

    public function test_cross_branch_queries_must_remove_the_scope_explicitly()
    {
        [$main, $other] = Branch::factory()->count(2)->create();
        Terminal::factory()->for($main)->create();
        Terminal::factory()->for($other)->create();

        $this->actingAs(User::factory()->for($main)->create());

        $this->assertSame(2, Terminal::withoutGlobalScope(BranchScope::class)->count());
    }

    public function test_new_records_take_the_signed_in_users_branch()
    {
        $branch = Branch::factory()->create();
        $this->actingAs(User::factory()->for($branch)->create());

        $terminal = Terminal::create(['code' => 'POS-09', 'name' => 'Counter 9']);

        $this->assertSame($branch->id, $terminal->branch_id);
    }

    public function test_an_explicit_branch_is_kept_on_create()
    {
        [$main, $other] = Branch::factory()->count(2)->create();
        $this->actingAs(User::factory()->for($main)->create());

        $terminal = Terminal::create(['branch_id' => $other->id, 'code' => 'POS-09', 'name' => 'Counter 9']);

        $this->assertSame($other->id, $terminal->branch_id);
    }

    public function test_a_record_cannot_be_saved_without_a_branch()
    {
        $this->expectException(QueryException::class);

        Terminal::create(['code' => 'POS-09', 'name' => 'Counter 9']);
    }

    public function test_terminal_codes_are_unique_within_a_branch_only()
    {
        [$main, $other] = Branch::factory()->count(2)->create();
        Terminal::factory()->for($main)->create(['code' => 'POS-01']);
        Terminal::factory()->for($other)->create(['code' => 'POS-01']);

        $this->expectException(QueryException::class);

        Terminal::factory()->for($main)->create(['code' => 'POS-01']);
    }

    public function test_a_branch_with_terminals_cannot_be_deleted()
    {
        $terminal = Terminal::factory()->create();

        $this->expectException(QueryException::class);

        $terminal->branch()->firstOrFail()->delete();
    }
}
