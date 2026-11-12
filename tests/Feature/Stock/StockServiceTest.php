<?php

namespace Tests\Feature\Stock;

use App\Enums\BatchStatus;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\Stock\InsufficientStockException;
use App\Services\Stock\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->branch = Branch::factory()->create();
        $this->product = Product::factory()->create();
    }

    private function receive(string $lot, string $expires, int $quantity, string $cost = '4.00'): void
    {
        $this->stock->receive($this->branch->id, $this->product, $lot, $expires, $quantity, $cost);
    }

    public function test_receiving_creates_the_batch_movement_and_balance()
    {
        $this->receive('A1', '2027-06-30', 100);

        $batch = Batch::sole();
        $this->assertSame('A1', $batch->lot_number);
        $this->assertSame(100, $this->stock->onHand($this->branch->id, $this->product->id));
        $this->assertSame(100, StockMovement::sole()->quantity);
        $this->assertSame(StockMovementType::Receipt, StockMovement::sole()->type);
    }

    public function test_issue_takes_the_earliest_expiry_first()
    {
        $this->receive('LATE', '2028-01-31', 50);
        $this->receive('EARLY', '2027-03-31', 50);

        $allocations = $this->stock->issue($this->branch->id, $this->product, 30, StockMovementType::Sale);

        $this->assertCount(1, $allocations);
        $this->assertSame(Batch::where('lot_number', 'EARLY')->sole()->id, $allocations[0]->batchId);
        $this->assertSame(30, $allocations[0]->quantity);
    }

    public function test_one_sale_line_can_split_across_two_batches()
    {
        $this->receive('EARLY', '2027-03-31', 10);
        $this->receive('LATE', '2028-01-31', 50);

        $allocations = $this->stock->issue($this->branch->id, $this->product, 25, StockMovementType::Sale);

        $this->assertSame([10, 15], array_map(fn ($allocation) => $allocation->quantity, $allocations));
        $this->assertSame(0, StockBalance::whereRelation('batch', 'lot_number', 'EARLY')->value('quantity'));
        $this->assertSame(35, StockBalance::whereRelation('batch', 'lot_number', 'LATE')->value('quantity'));
        $this->assertSame(-25, (int) StockMovement::where('type', 'sale')->sum('quantity'));
    }

    public function test_expired_batches_are_never_allocated()
    {
        $this->receive('OLD', now()->subDay()->toDateString(), 100);
        $this->receive('GOOD', now()->addYear()->toDateString(), 5);

        $this->assertSame(5, $this->stock->sellableOnHand($this->branch->id, $this->product->id));

        $this->expectException(InsufficientStockException::class);
        $this->stock->issue($this->branch->id, $this->product, 6, StockMovementType::Sale);
    }

    public function test_a_batch_expiring_today_can_still_be_sold()
    {
        $this->receive('TODAY', now()->toDateString(), 5);

        $this->assertCount(1, $this->stock->issue($this->branch->id, $this->product, 5, StockMovementType::Sale));
    }

    public function test_quarantined_batches_are_never_allocated()
    {
        $this->receive('HOLD', '2027-06-30', 100);
        Batch::sole()->update(['status' => BatchStatus::Quarantined]);

        $this->expectException(InsufficientStockException::class);
        $this->stock->issue($this->branch->id, $this->product, 1, StockMovementType::Sale);
    }

    public function test_insufficient_stock_changes_nothing()
    {
        $this->receive('A1', '2027-06-30', 10);

        try {
            $this->stock->issue($this->branch->id, $this->product, 11, StockMovementType::Sale);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(10, $exception->available);
        }

        $this->assertSame(10, $this->stock->onHand($this->branch->id, $this->product->id));
        $this->assertSame(1, StockMovement::count());
    }

    public function test_stock_in_another_branch_is_not_used()
    {
        $other = Branch::factory()->create();
        $this->stock->receive($other->id, $this->product, 'X', '2027-06-30', 100, '4.00');

        $this->expectException(InsufficientStockException::class);
        $this->stock->issue($this->branch->id, $this->product, 1, StockMovementType::Sale);
    }

    public function test_unit_conversion_is_applied_by_the_caller_in_base_units()
    {
        $box = $this->product->units()->where('name', 'Box')->sole();
        $this->receive('A1', '2027-06-30', $box->toBase(2));

        $this->stock->issue($this->branch->id, $this->product, $box->toBase(1), StockMovementType::Sale);

        $this->assertSame(100, $this->stock->onHand($this->branch->id, $this->product->id));
    }

    public function test_receiving_updates_the_moving_average_cost()
    {
        $this->receive('A1', '2027-06-30', 100, '4.00');
        $this->receive('B1', '2027-09-30', 300, '5.00');

        // (100 × 4 + 300 × 5) ÷ 400 = 4.75
        $this->assertSame('4.7500', $this->product->fresh()?->average_cost);
    }

    public function test_receiving_the_same_lot_again_averages_its_cost()
    {
        $this->receive('A1', '2027-06-30', 100, '4.00');
        $this->receive('A1', '2027-06-30', 100, '4.50');

        $this->assertSame(1, Batch::count());
        $this->assertSame('4.2500', Batch::sole()->unit_cost);
        $this->assertSame(200, $this->stock->onHand($this->branch->id, $this->product->id));
    }

    public function test_a_supervisor_can_issue_from_a_named_batch()
    {
        $this->receive('EARLY', '2027-03-31', 10);
        $this->receive('LATE', '2028-01-31', 10);
        $late = Batch::where('lot_number', 'LATE')->sole();

        $allocations = $this->stock->issue($this->branch->id, $this->product, 5, StockMovementType::Sale, batchId: $late->id);

        $this->assertSame($late->id, $allocations[0]->batchId);
    }

    public function test_adjust_cannot_take_a_batch_below_zero()
    {
        $this->receive('A1', '2027-06-30', 3);

        $this->expectException(InsufficientStockException::class);
        $this->stock->adjust($this->branch->id, Batch::sole(), -4, StockMovementType::Adjustment);
    }

    public function test_returned_stock_goes_back_to_the_same_batch()
    {
        $this->receive('A1', '2027-06-30', 10);
        $allocation = $this->stock->issue($this->branch->id, $this->product, 4, StockMovementType::Sale)[0];

        $this->stock->adjust($this->branch->id, Batch::findOrFail($allocation->batchId), 4, StockMovementType::SaleVoid);

        $this->assertSame(10, $this->stock->onHand($this->branch->id, $this->product->id));
    }

    public function test_movements_cannot_be_changed_or_deleted()
    {
        $this->receive('A1', '2027-06-30', 10);
        $movement = StockMovement::sole();

        try {
            $movement->update(['quantity' => 999]);
            $this->fail('Movements must not be updatable.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_the_database_refuses_a_negative_balance()
    {
        $this->receive('A1', '2027-06-30', 10);

        $this->expectException(QueryException::class);
        DB::table('stock_balances')->update(['quantity' => -1]);
    }

    public function test_quantities_must_be_positive()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->stock->issue($this->branch->id, $this->product, 0, StockMovementType::Sale);
    }
}
