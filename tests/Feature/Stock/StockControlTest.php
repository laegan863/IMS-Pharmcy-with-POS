<?php

namespace Tests\Feature\Stock;

use App\Enums\BatchStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ExpiryAlert;
use App\Services\Stock\InsufficientStockException;
use App\Services\Stock\StockControlService;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockControlTest extends TestCase
{
    use RefreshDatabase;

    private User $clerk;

    private User $pharmacist;

    private Product $product;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clerk = User::factory()->create()->assignRole(Role::InventoryClerk);
        $this->pharmacist = User::factory()->create(['branch_id' => $this->clerk->branch_id])->assignRole(Role::Pharmacist);
        $this->product = Product::factory()->create();
        app(StockService::class)->receive($this->clerk->branch_id, $this->product, 'L1', now()->addYear()->toDateString(), 50, '4.00');
        $this->batch = Batch::sole();
    }

    private function control(): StockControlService
    {
        return app(StockControlService::class);
    }

    private function onHand(?int $branchId = null): int
    {
        return app(StockService::class)->onHand($branchId ?? $this->clerk->branch_id, $this->product->id);
    }

    public function test_an_adjustment_is_drafted_by_a_clerk_and_posted_by_a_pharmacist()
    {
        $adjustment = $this->control()->saveAdjustment(null, $this->clerk->branch_id, 'adjustment', ['reason_code' => 'damaged', 'note' => 'Crushed box'], [
            ['batch_id' => $this->batch->id, 'quantity_change' => -5],
        ], $this->clerk);

        $this->assertSame('-20.00', $adjustment->value);
        $this->assertSame(50, $this->onHand());

        try {
            $this->control()->postAdjustment($adjustment, $this->clerk);
            $this->fail('Clerk posted an adjustment.');
        } catch (ValidationException) {
        }

        $this->control()->postAdjustment($adjustment, $this->pharmacist);
        $this->assertSame(45, $this->onHand());
        $this->assertStringStartsWith('ADJ-', (string) $adjustment->fresh()?->number);
    }

    public function test_an_adjustment_cannot_take_more_than_is_on_hand()
    {
        $adjustment = $this->control()->saveAdjustment(null, $this->clerk->branch_id, 'adjustment', ['reason_code' => 'lost'], [
            ['batch_id' => $this->batch->id, 'quantity_change' => -51],
        ], $this->clerk);

        $this->expectException(ValidationException::class);
        $this->control()->postAdjustment($adjustment, $this->pharmacist);
    }

    public function test_disposing_a_whole_batch_marks_it_disposed()
    {
        try {
            $this->control()->saveAdjustment(null, $this->clerk->branch_id, 'disposal', ['reason_code' => 'expired'], [['batch_id' => $this->batch->id, 'quantity_change' => 50]], $this->clerk);
            $this->fail('Disposal without witness accepted.');
        } catch (ValidationException) {
        }

        $disposal = $this->control()->saveAdjustment(null, $this->clerk->branch_id, 'disposal', [
            'reason_code' => 'expired',
            'disposal_method' => 'Returned to DENR-accredited hauler',
            'witness_name' => 'Juan Witness',
        ], [['batch_id' => $this->batch->id, 'quantity_change' => 50]], $this->clerk);

        $this->control()->postAdjustment($disposal, $this->pharmacist);

        $this->assertSame(0, $this->onHand());
        $this->assertSame(BatchStatus::Disposed, $this->batch->fresh()?->status);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovementType::Disposal->value, 'quantity' => -50]);
    }

    public function test_a_count_posts_only_the_variance_even_with_sales_during_counting()
    {
        $count = $this->control()->startCount($this->clerk->branch_id, [], null, $this->clerk);
        $line = $count->lines()->sole();
        $this->assertSame(50, $line->system_quantity);

        // 3 sold while the shelf was being counted; the counter saw 46 (4 short before the sales).
        app(StockService::class)->issue($this->clerk->branch_id, $this->product, 3, StockMovementType::Sale);
        $this->control()->recordCounts($count, [$line->id => 46]);

        try {
            $this->control()->postCount($count, $this->pharmacist);
            $this->fail('Posted without review.');
        } catch (ValidationException) {
        }

        $this->control()->review($count, $this->clerk);
        $this->control()->postCount($count->fresh() ?? $count, $this->pharmacist);

        // 50 − 3 sold − 4 variance = 43.
        $this->assertSame(43, $this->onHand());
        $this->assertDatabaseHas('stock_movements', ['type' => 'count_variance', 'quantity' => -4]);
    }

    public function test_review_needs_every_line_counted()
    {
        $count = $this->control()->startCount($this->clerk->branch_id, [], null, $this->clerk);

        $this->expectException(ValidationException::class);
        $this->control()->review($count, $this->clerk);
    }

    public function test_a_transfer_moves_the_same_batch_between_branches()
    {
        $other = Branch::factory()->create();
        $receiver = User::factory()->create(['branch_id' => $other->id])->assignRole(Role::InventoryClerk);

        $transfer = $this->control()->saveTransfer(null, $this->clerk->branch_id, $other->id, 'Weekly top-up', [
            ['batch_id' => $this->batch->id, 'quantity_base' => 20],
        ], $this->clerk);

        $this->control()->dispatch($transfer, $this->clerk);
        $this->assertSame(30, $this->onHand());
        $this->assertSame(0, $this->onHand($other->id));
        $this->assertSame('in_transit', $transfer->fresh()?->status);

        try {
            $this->control()->receive($transfer, $this->clerk);
            $this->fail('Sending branch received its own transfer.');
        } catch (ValidationException) {
        }

        $this->control()->receive($transfer, $receiver);
        $this->assertSame(20, $this->onHand($other->id));
        $this->assertSame(1, Batch::count());
    }

    public function test_quarantined_batches_cannot_be_sold_until_released()
    {
        $this->control()->quarantine($this->batch, 'FDA recall notice 2026-11');

        try {
            app(StockService::class)->issue($this->clerk->branch_id, $this->product, 1, StockMovementType::Sale);
            $this->fail('Sold from quarantine.');
        } catch (InsufficientStockException) {
        }

        $this->control()->release($this->batch->fresh() ?? $this->batch);
        $this->assertCount(1, app(StockService::class)->issue($this->clerk->branch_id, $this->product, 1, StockMovementType::Sale));
    }

    public function test_the_daily_check_expires_batches_and_alerts_at_thresholds()
    {
        Notification::fake();
        $soon = Product::factory()->create();
        app(StockService::class)->receive($this->clerk->branch_id, $soon, 'S30', now()->addDays(30)->toDateString(), 10, '1.00');
        app(StockService::class)->receive($this->clerk->branch_id, $soon, 'OLD', now()->subDays(2)->toDateString(), 10, '1.00');

        $this->artisan('stock:daily-expiry-check')->assertSuccessful();

        $this->assertSame(BatchStatus::Expired, Batch::where('lot_number', 'OLD')->sole()->status);
        Notification::assertSentTo($this->clerk, ExpiryAlert::class, fn (ExpiryAlert $alert) => collect($alert->batches)->pluck('lot')->all() === ['S30']);
        Notification::assertSentTo($this->pharmacist, ExpiryAlert::class);
    }
}
