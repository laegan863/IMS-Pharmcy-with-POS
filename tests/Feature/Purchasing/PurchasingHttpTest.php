<?php

namespace Tests\Feature\Purchasing;

use App\Enums\Role;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchasingHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $clerk;

    private User $pharmacist;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clerk = User::factory()->create()->assignRole(Role::InventoryClerk);
        $this->pharmacist = User::factory()->withTwoFactor()->create(['branch_id' => $this->clerk->branch_id])->assignRole(Role::Pharmacist);
        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create();
    }

    private function boxId(): int
    {
        return $this->product->units()->where('name', 'Box')->value('id');
    }

    public function test_a_clerk_drafts_and_a_pharmacist_approves_then_the_clerk_receives()
    {
        $this->actingAs($this->clerk)->post(route('purchasing.orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'product_unit_id' => $this->boxId(), 'quantity' => 2, 'unit_cost' => '400']],
        ])->assertSessionHasNoErrors();

        $order = PurchaseOrder::sole();

        $this->actingAs($this->clerk)->post(route('purchasing.orders.approve', $order))->assertForbidden();
        $this->actingAs($this->pharmacist)->post(route('purchasing.orders.approve', $order))->assertSessionHasNoErrors();

        $this->actingAs($this->clerk)
            ->get(route('purchasing.receipts.create', ['purchase_order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('receipt.purchase_order_id', $order->id)
                ->where('receipt.lines.0.quantity', 2));

        $this->actingAs($this->clerk)->post(route('purchasing.receipts.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $order->id,
            'supplier_invoice_number' => 'INV-889',
            'post' => true,
            'acknowledge_warnings' => true,
            'lines' => [[
                'purchase_order_line_id' => $order->lines()->sole()->id,
                'product_id' => $this->product->id,
                'product_unit_id' => $this->boxId(),
                'lot_number' => 'L1',
                'expires_on' => now()->addYears(2)->toDateString(),
                'quantity' => 2,
                'free_quantity' => 0,
                'unit_cost' => '400',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(GoodsReceipt::sole()->number);
        $this->assertSame(200, app(StockService::class)->onHand($this->clerk->branch_id, $this->product->id));
        $this->assertSame('received', $order->fresh()?->status->value);
    }

    public function test_a_receipt_line_needs_a_batch_number_and_expiry()
    {
        $this->actingAs($this->clerk)->post(route('purchasing.receipts.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'product_unit_id' => $this->boxId(), 'lot_number' => '', 'expires_on' => '', 'quantity' => 1, 'unit_cost' => '1']],
        ])->assertSessionHasErrors(['lines.0.lot_number', 'lines.0.expires_on']);
    }

    public function test_unacknowledged_warnings_keep_the_draft_and_show_them()
    {
        $this->actingAs($this->clerk)->post(route('purchasing.receipts.store'), [
            'supplier_id' => $this->supplier->id,
            'post' => true,
            'lines' => [[
                'product_id' => $this->product->id,
                'product_unit_id' => $this->boxId(),
                'lot_number' => 'SHORT',
                'expires_on' => now()->addMonth()->toDateString(),
                'quantity' => 1,
                'unit_cost' => '400',
            ]],
        ])->assertSessionHasErrors('warnings');

        $receipt = GoodsReceipt::sole();
        $this->assertSame('draft', $receipt->status->value);
        $this->assertSame(0, app(StockService::class)->onHand($this->clerk->branch_id, $this->product->id));
    }

    public function test_receipts_of_another_branch_are_not_visible()
    {
        $other = User::factory()->create()->assignRole(Role::InventoryClerk);
        $receipt = GoodsReceipt::query()->create([
            'branch_id' => $other->branch_id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->clerk)->get(route('purchasing.receipts.show', $receipt))->assertNotFound();
    }

    public function test_cashiers_cannot_reach_purchasing()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)->get(route('purchasing.orders.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('purchasing.receipts.index'))->assertForbidden();
    }

    public function test_the_reorder_list_renders()
    {
        $this->actingAs($this->clerk)
            ->get(route('purchasing.reorder'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('suggestions', 1));
    }
}
