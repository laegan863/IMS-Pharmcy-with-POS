<?php

namespace Tests\Feature\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\ReorderSuggestions;
use App\Services\Purchasing\SupplierReturnService;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create(['reorder_point' => 100]);
    }

    private function box(): int
    {
        return $this->product->units()->where('name', 'Box')->value('id');
    }

    private function approvedOrder(int $boxes = 5, string $cost = '400.00'): PurchaseOrder
    {
        $orders = app(PurchaseOrderService::class);
        $order = $orders->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id], [
            ['product_id' => $this->product->id, 'product_unit_id' => $this->box(), 'quantity' => $boxes, 'unit_cost' => $cost],
        ], $this->user);
        $orders->approve($order, $this->user);

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receiptLine(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->product->id,
            'product_unit_id' => $this->box(),
            'lot_number' => 'L1',
            'expires_on' => now()->addYears(2)->toDateString(),
            'quantity' => 5,
            'free_quantity' => 0,
            'unit_cost' => '400.00',
        ], $overrides);
    }

    public function test_purchase_orders_are_numbered_totalled_and_never_move_stock()
    {
        $order = $this->approvedOrder();

        $this->assertMatchesRegularExpression('/^PO-BR-\d{3}-00000001$/', $order->number);
        $this->assertSame('2000.00', $order->total);
        $this->assertSame(500, $order->lines()->sole()->quantity_base);
        $this->assertSame(PurchaseOrderStatus::Approved, $order->status);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_a_failed_posting_does_not_use_up_a_number()
    {
        $numbers = app(DocumentNumbers::class);

        try {
            DB::transaction(function () use ($numbers) {
                $numbers->next(DocumentNumbers::GOODS_RECEIPT, $this->user->branch_id);

                throw new \RuntimeException('posting failed');
            });
        } catch (\RuntimeException) {
        }

        $number = DB::transaction(fn () => $numbers->next(DocumentNumbers::GOODS_RECEIPT, $this->user->branch_id));

        $this->assertStringEndsWith('00000001', $number);
    }

    public function test_posting_a_receipt_adds_stock_against_the_order()
    {
        $order = $this->approvedOrder();
        $receipts = app(GoodsReceiptService::class);

        $receipt = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id, 'purchase_order_id' => $order->id], [
            $this->receiptLine(['purchase_order_line_id' => $order->lines()->sole()->id, 'quantity' => 3]),
        ], $this->user);

        $receipts->post($receipt, $this->user, acknowledgeWarnings: true);

        $this->assertSame(300, app(StockService::class)->onHand($this->user->branch_id, $this->product->id));
        $this->assertSame(PurchaseOrderStatus::PartlyReceived, $order->fresh()?->status);
        $this->assertNotNull($receipt->fresh()?->number);
        $this->assertSame('4.0000', Batch::sole()->unit_cost);

        $second = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id, 'purchase_order_id' => $order->id], [
            $this->receiptLine(['purchase_order_line_id' => $order->lines()->sole()->id, 'quantity' => 2, 'lot_number' => 'L2']),
        ], $this->user);
        $receipts->post($second, $this->user, acknowledgeWarnings: true);

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()?->status);
    }

    public function test_free_goods_lower_the_cost_per_base_unit()
    {
        $receipts = app(GoodsReceiptService::class);
        $receipt = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id], [
            $this->receiptLine(['quantity' => 10, 'free_quantity' => 1, 'unit_cost' => '440.00']),
        ], $this->user);

        $receipts->post($receipt, $this->user, acknowledgeWarnings: true);

        // 10 × 440 ÷ 1,100 tablets = 4.00
        $this->assertSame('4.0000', Batch::sole()->unit_cost);
        $this->assertSame(1100, app(StockService::class)->onHand($this->user->branch_id, $this->product->id));
    }

    public function test_short_shelf_life_and_cost_changes_need_acknowledging()
    {
        $receipts = app(GoodsReceiptService::class);
        $first = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id], [$this->receiptLine()], $this->user);
        $receipts->post($first, $this->user, acknowledgeWarnings: false);

        $second = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id], [
            $this->receiptLine(['lot_number' => 'L2', 'expires_on' => now()->addMonths(2)->toDateString(), 'unit_cost' => '450.00']),
        ], $this->user);

        try {
            $receipts->post($second, $this->user, acknowledgeWarnings: false);
            $this->fail('Expected warnings.');
        } catch (ValidationException $exception) {
            $warnings = $exception->errors()['warnings'];
            $this->assertCount(2, $warnings);
            $this->assertStringContainsString('minimum shelf life', $warnings[0]);
            $this->assertStringContainsString('4.50', $warnings[1]);
        }

        $this->assertNull($second->fresh()?->number);

        $receipts->post($second, $this->user, acknowledgeWarnings: true);
        $this->assertNotNull($second->fresh()?->number);
    }

    public function test_expired_goods_cannot_be_received()
    {
        $receipts = app(GoodsReceiptService::class);
        $receipt = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id], [
            $this->receiptLine(['expires_on' => now()->subDay()->toDateString()]),
        ], $this->user);

        $this->expectException(ValidationException::class);
        $receipts->post($receipt, $this->user, acknowledgeWarnings: true);
    }

    public function test_receiving_more_than_ordered_is_refused_and_nothing_is_saved()
    {
        $order = $this->approvedOrder(boxes: 2);
        $receipts = app(GoodsReceiptService::class);
        $receipt = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id, 'purchase_order_id' => $order->id], [
            $this->receiptLine(['purchase_order_line_id' => $order->lines()->sole()->id, 'quantity' => 3]),
        ], $this->user);

        try {
            $receipts->post($receipt, $this->user, acknowledgeWarnings: true);
            $this->fail('Expected over-receipt to fail.');
        } catch (ValidationException) {
        }

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertNull($receipt->fresh()?->number);
    }

    public function test_a_posted_receipt_cannot_be_edited_or_posted_twice()
    {
        $receipts = app(GoodsReceiptService::class);
        $receipt = $receipts->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id], [$this->receiptLine()], $this->user);
        $receipts->post($receipt, $this->user, acknowledgeWarnings: true);

        $this->expectException(ValidationException::class);
        $receipts->post($receipt->fresh() ?? $receipt, $this->user, acknowledgeWarnings: true);
    }

    public function test_a_supplier_return_takes_stock_from_the_named_batch()
    {
        $stock = app(StockService::class);
        $stock->receive($this->user->branch_id, $this->product, 'BAD', now()->addYear()->toDateString(), 50, '4.00');
        $batch = Batch::sole();

        $returns = app(SupplierReturnService::class);
        $return = $returns->saveDraft(null, $this->user->branch_id, ['supplier_id' => $this->supplier->id, 'reason' => 'Damaged'], [
            ['batch_id' => $batch->id, 'quantity_base' => 20],
        ], $this->user);
        $returns->post($return, $this->user);

        $this->assertSame(30, $stock->onHand($this->user->branch_id, $this->product->id));
        $this->assertSame('80.00', $return->fresh()?->total);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovementType::SupplierReturn->value, 'quantity' => -20]);
    }

    public function test_reorder_suggestions_round_up_to_purchase_units()
    {
        app(StockService::class)->receive($this->user->branch_id, $this->product, 'L1', now()->addYear()->toDateString(), 40, '4.00');

        $suggestions = app(ReorderSuggestions::class)->forBranch($this->user->branch_id);

        $this->assertCount(1, $suggestions);
        // Target 2 × 100 = 200, on hand 40, need 160 tablets = 2 boxes of 100.
        $this->assertSame(2, $suggestions[0]['quantity']);
        $this->assertSame('Box', $suggestions[0]['unit']);
    }
}
