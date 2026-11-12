<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);
    }

    public function test_the_owner_sees_terminals_of_every_branch()
    {
        $other = Branch::factory()->create();
        Terminal::factory()->for($other)->count(2)->create();

        $this->actingAs($this->owner)
            ->get(route('admin.branches.edit', $other))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('terminals', 2));

        $this->actingAs($this->owner)
            ->get(route('admin.branches.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data', fn ($rows) => collect($rows)->firstWhere('id', $other->id)['terminals_count'] === 2));
    }

    public function test_the_owner_adds_and_edits_a_terminal_in_another_branch()
    {
        $other = Branch::factory()->create();

        $this->actingAs($this->owner)->post(route('admin.branches.terminals.store', $other), [
            'code' => 'POS-02',
            'name' => 'Counter 2',
            'machine_identification_number' => '12345678901234',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $terminal = Terminal::withoutGlobalScope(BranchScope::class)->where('code', 'POS-02')->sole();
        $this->assertSame($other->id, $terminal->branch_id);

        $this->actingAs($this->owner)->put(route('admin.branches.terminals.update', [$other, $terminal->id]), [
            'code' => 'POS-02',
            'name' => 'Pharmacy counter',
            'is_active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Pharmacy counter', $terminal->fresh()?->name);
    }

    public function test_terminal_codes_must_be_unique_within_the_branch()
    {
        $branch = Branch::factory()->create();
        Terminal::factory()->for($branch)->create(['code' => 'POS-01']);

        $this->actingAs($this->owner)
            ->post(route('admin.branches.terminals.store', $branch), ['code' => 'POS-01', 'name' => 'Dup'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_terminal_cannot_be_edited_through_another_branch()
    {
        [$a, $b] = Branch::factory()->count(2)->create();
        $terminal = Terminal::factory()->for($a)->create();

        $this->actingAs($this->owner)
            ->put(route('admin.branches.terminals.update', [$b, $terminal->id]), ['code' => 'X', 'name' => 'X'])
            ->assertNotFound();
    }
}
