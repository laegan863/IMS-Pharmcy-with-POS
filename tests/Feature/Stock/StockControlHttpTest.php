<?php

namespace Tests\Feature\Stock;

use App\Enums\BatchStatus;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockControlHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $clerk;

    private User $pharmacist;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clerk = User::factory()->create()->assignRole(Role::InventoryClerk);
        $this->pharmacist = User::factory()->withTwoFactor()->create(['branch_id' => $this->clerk->branch_id])->assignRole(Role::Pharmacist);
        app(StockService::class)->receive($this->clerk->branch_id, Product::factory()->create(), 'L1', now()->addYear()->toDateString(), 50, '4.00');
        $this->batch = Batch::sole();
    }

    public function test_clerk_drafts_and_pharmacist_posts_an_adjustment()
    {
        $this->actingAs($this->clerk)->post(route('stock.adjustments.store', 'adjustment'), [
            'reason_code' => 'damaged',
            'lines' => [['batch_id' => $this->batch->id, 'quantity_change' => -2]],
            'post' => true,
        ])->assertSessionHasErrors('status');

        $adjustment = StockAdjustment::sole();
        $this->assertSame('draft', $adjustment->status);

        $this->actingAs($this->pharmacist)
            ->get(route('stock.adjustments.show', $adjustment))
            ->assertInertia(fn (Assert $page) => $page->where('canPost', true));

        $this->actingAs($this->pharmacist)->put(route('stock.adjustments.update', $adjustment), [
            'reason_code' => 'damaged',
            'lines' => [['batch_id' => $this->batch->id, 'quantity_change' => -2]],
            'post' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('posted', $adjustment->fresh()?->status);
    }

    public function test_a_count_from_start_to_post()
    {
        $this->actingAs($this->clerk)->post(route('stock.counts.store'))->assertRedirect();
        $count = StockCount::sole();
        $line = $count->lines()->sole();

        $this->actingAs($this->clerk)->put(route('stock.counts.update', $count), ['counts' => [$line->id => 48], 'action' => 'review'])->assertSessionHasNoErrors();
        $this->actingAs($this->pharmacist)->put(route('stock.counts.update', $count), ['counts' => [$line->id => 48], 'action' => 'post'])->assertSessionHasNoErrors();

        $this->assertSame('posted', $count->fresh()?->status);
        $this->assertSame(48, app(StockService::class)->onHand($this->clerk->branch_id, $this->batch->product_id));
    }

    public function test_transfers_are_visible_to_both_branches_only()
    {
        $other = Branch::factory()->create();
        $this->actingAs($this->clerk)->post(route('stock.transfers.store'), [
            'to_branch_id' => $other->id,
            'lines' => [['batch_id' => $this->batch->id, 'quantity_base' => 5]],
            'dispatch' => true,
        ])->assertSessionHasNoErrors();

        $transfer = StockTransfer::sole();
        $receiver = User::factory()->create(['branch_id' => $other->id])->assignRole(Role::InventoryClerk);
        $outsider = User::factory()->create()->assignRole(Role::InventoryClerk);

        $this->actingAs($receiver)->get(route('stock.transfers.show', $transfer))->assertOk();
        $this->actingAs($outsider)->get(route('stock.transfers.show', $transfer))->assertNotFound();

        $this->actingAs($receiver)->post(route('stock.transfers.receive', $transfer))->assertSessionHasNoErrors();
        $this->assertSame('received', $transfer->fresh()?->status);
    }

    public function test_batch_page_shows_recall_trace_and_quarantine()
    {
        $this->actingAs($this->pharmacist)
            ->get(route('stock.batch', $this->batch))
            ->assertInertia(fn (Assert $page) => $page->has('soldTo', 0)->where('canQuarantine', true));

        $this->actingAs($this->pharmacist)->post(route('stock.batches.quarantine', $this->batch), ['reason' => 'Recall'])->assertSessionHasNoErrors();
        $this->assertSame(BatchStatus::Quarantined, $this->batch->fresh()?->status);

        $this->actingAs($this->pharmacist)->post(route('stock.batches.release', $this->batch))->assertSessionHasNoErrors();
        $this->assertSame(BatchStatus::Active, $this->batch->fresh()?->status);
    }

    public function test_cashiers_cannot_adjust_or_count()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)->get(route('stock.adjustments.index', 'adjustment'))->assertForbidden();
        $this->actingAs($cashier)->get(route('stock.counts.index'))->assertForbidden();
        $this->actingAs($cashier)->post(route('stock.batches.quarantine', $this->batch), ['reason' => 'x'])->assertForbidden();
    }
}
