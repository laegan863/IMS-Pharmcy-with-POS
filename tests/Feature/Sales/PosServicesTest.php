<?php

namespace Tests\Feature\Sales;

use App\Enums\Role;
use App\Enums\TaxClass;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Reading;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Sales\SaleAdjustmentService;
use App\Services\Sales\SaleService;
use App\Services\Sales\ShiftService;
use App\Services\Sales\ZReadingService;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosServicesTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $supervisor;

    private Terminal $terminal;

    private Shift $shift;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cashier = User::factory()->create()->assignRole(Role::Cashier);
        $this->supervisor = User::factory()->create([
            'branch_id' => $this->cashier->branch_id,
            'username' => 'pharma',
            'supervisor_pin' => Hash::make('2468'),
        ])->assignRole(Role::Pharmacist);
        $this->terminal = Terminal::factory()->create(['branch_id' => $this->cashier->branch_id, 'code' => 'POS-01']);
        $this->product = Product::factory()->create(['tax_class' => TaxClass::Vatable]);
        $this->product->units()->where('name', 'Tablet')->update(['price' => '100.00']);

        app(StockService::class)->receive($this->cashier->branch_id, $this->product, 'EARLY', now()->addMonths(6)->toDateString(), 5, '60.00');
        app(StockService::class)->receive($this->cashier->branch_id, $this->product, 'LATE', now()->addYear()->toDateString(), 50, '60.00');

        $this->shift = app(ShiftService::class)->open($this->cashier, $this->terminal, '1000.00');
    }

    private function tablet(): int
    {
        return $this->product->units()->where('name', 'Tablet')->value('id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sell(int $quantity = 2, array $overrides = []): Sale
    {
        return app(SaleService::class)->complete(array_merge([
            'client_uuid' => (string) Str::uuid(),
            'lines' => [['product_id' => $this->product->id, 'product_unit_id' => $this->tablet(), 'quantity' => $quantity]],
            'discount_type' => 'none',
            'payments' => [['method' => 'cash', 'amount' => '1000.00']],
        ], $overrides), $this->cashier, $this->shift);
    }

    public function test_a_cash_sale_posts_with_number_vat_change_and_fefo_batches()
    {
        $sale = $this->sell(7);

        $this->assertSame('SI-'.$this->terminal->branch->code.'-POS-01-00000001', $sale->number);
        $this->assertSame('700.00', $sale->total);
        $this->assertSame('75.00', $sale->vat_amount);
        $this->assertSame('625.00', $sale->vatable_sales);
        $this->assertSame('300.00', $sale->change_due);
        $this->assertSame('420.0000', $sale->cost_total);

        $batches = $sale->lines()->sole()->batches()->with('batch')->get();
        $this->assertSame(['EARLY' => 5, 'LATE' => 2], $batches->mapWithKeys(fn ($b) => [$b->batch->lot_number => $b->quantity_base])->all());
        $this->assertSame('700.00', $this->terminal->fresh()?->grand_total);
    }

    public function test_the_same_cart_posted_twice_creates_one_sale()
    {
        $uuid = (string) Str::uuid();
        $first = $this->sell(1, ['client_uuid' => $uuid]);
        $second = $this->sell(1, ['client_uuid' => $uuid]);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Sale::count());
        $this->assertSame(54, app(StockService::class)->onHand($this->cashier->branch_id, $this->product->id));
    }

    public function test_a_senior_sale_follows_the_official_computation()
    {
        $sale = $this->sell(1, ['discount_type' => 'senior', 'discount_id_number' => 'SC-123', 'discount_holder_name' => 'Lola Remedios']);

        $this->assertSame('71.43', $sale->total);
        $this->assertSame('17.86', $sale->statutory_discount);
        $this->assertSame('89.29', $sale->vat_exempt_sales);
        $this->assertSame('SC-123', $sale->discount_id_number);
        $this->assertStringNotContainsString('SC-123', (string) DB::table('sales')->value('discount_id_number'));
    }

    public function test_a_discount_needs_the_id_number_and_name()
    {
        $this->expectException(ValidationException::class);
        $this->sell(1, ['discount_type' => 'pwd']);
    }

    public function test_short_payment_and_card_overpayment_are_refused()
    {
        try {
            $this->sell(2, ['payments' => [['method' => 'cash', 'amount' => '150.00']]]);
            $this->fail('Short payment accepted.');
        } catch (ValidationException) {
        }

        try {
            $this->sell(2, ['payments' => [['method' => 'card', 'amount' => '500.00', 'reference' => 'APPR1']]]);
            $this->fail('Card overpayment accepted.');
        } catch (ValidationException) {
        }

        $this->assertSame(0, Sale::count());
    }

    public function test_split_payment_cash_and_ewallet()
    {
        $sale = $this->sell(3, ['payments' => [
            ['method' => 'ewallet', 'amount' => '200.00', 'reference' => 'GCASH-77'],
            ['method' => 'cash', 'amount' => '500.00'],
        ]]);

        $this->assertSame('400.00', $sale->change_due);
        $this->assertSame(2, $sale->payments()->count());
    }

    public function test_selling_more_than_in_stock_saves_nothing_and_uses_no_number()
    {
        try {
            $this->sell(56);
            $this->fail('Oversell accepted.');
        } catch (ValidationException) {
        }

        $this->assertSame(0, Sale::count());
        $this->assertStringEndsWith('00000001', $this->sell(1)->number);
    }

    public function test_a_price_override_needs_a_supervisor_pin()
    {
        $lines = [['product_id' => $this->product->id, 'product_unit_id' => $this->tablet(), 'quantity' => 1, 'unit_price' => '80.00']];

        try {
            $this->sell(1, ['lines' => $lines]);
            $this->fail('Override without PIN accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approver_pin', $exception->errors());
        }

        $sale = $this->sell(1, ['lines' => $lines, 'approver' => 'pharma', 'approver_pin' => '2468']);
        $line = $sale->lines()->sole();
        $this->assertSame('80.00', $line->unit_price);
        $this->assertSame('100.00', $line->list_price);
        $this->assertSame($this->supervisor->id, $line->price_override_by);
    }

    public function test_a_void_returns_stock_to_the_same_batches_with_its_own_number()
    {
        $sale = $this->sell(7);
        $void = app(SaleAdjustmentService::class)->void($sale, $this->shift, $this->cashier, 'Wrong item', 'pharma', '2468');

        $this->assertStringStartsWith('VD-', $void->number);
        $this->assertSame('voided', $sale->fresh()?->status);
        $this->assertSame(5, DB::table('stock_balances')->where('batch_id', Batch::where('lot_number', 'EARLY')->value('id'))->value('quantity'));
        $this->assertSame(55, app(StockService::class)->onHand($this->cashier->branch_id, $this->product->id));
    }

    public function test_a_void_needs_the_right_supervisor()
    {
        $sale = $this->sell(1);

        $this->expectException(ValidationException::class);
        app(SaleAdjustmentService::class)->void($sale, $this->shift, $this->cashier, 'Oops', 'pharma', '0000');
    }

    public function test_a_partial_refund_returns_its_share_and_stock()
    {
        $sale = $this->sell(1, ['discount_type' => 'senior', 'discount_id_number' => 'SC-1', 'discount_holder_name' => 'Lolo']);
        $sale2 = $this->sell(4);
        $line = $sale2->lines()->sole();

        $refund = app(SaleAdjustmentService::class)->refund($sale2, $this->shift, $this->cashier, [$line->id => 1], 'Changed mind', 'pharma', '2468');

        $this->assertSame('100.00', $refund->amount);
        $this->assertStringStartsWith('RF-', $refund->number);
        $this->assertSame(3, $line->fresh()?->refundableBase());
        $this->assertSame(51, app(StockService::class)->onHand($this->cashier->branch_id, $this->product->id));

        $this->expectException(ValidationException::class);
        app(SaleAdjustmentService::class)->refund($sale2, $this->shift, $this->cashier, [$line->id => 4], 'Again', 'pharma', '2468');
        unset($sale);
    }

    public function test_closing_a_shift_counts_cash_and_stores_the_x_reading()
    {
        $this->sell(2);
        app(ShiftService::class)->cashMovement($this->shift, $this->cashier, 'out', '50.00', 'Ice for the cooler');

        $shift = app(ShiftService::class)->close($this->shift, ['1000' => 1, '100' => 1, '50' => 1]);

        // 1,000 float + 200 sales − 50 paid out = 1,150 expected.
        $this->assertSame('1150.00', $shift->expected_cash);
        $this->assertSame('1150.00', $shift->counted_cash);
        $this->assertSame('0.00', $shift->variance);
        $this->assertSame(1, $shift->x_reading['invoice_count'] ?? null);
    }

    public function test_the_z_reading_matches_the_sales_and_keeps_the_grand_total()
    {
        $this->sell(2);
        $this->sell(1, ['discount_type' => 'senior', 'discount_id_number' => 'SC-1', 'discount_holder_name' => 'Lolo']);
        $voided = $this->sell(1);
        app(SaleAdjustmentService::class)->void($voided, $this->shift, $this->cashier, 'Test', 'pharma', '2468');

        try {
            app(ZReadingService::class)->generate($this->terminal, $this->supervisor);
            $this->fail('Z-reading with an open shift.');
        } catch (ValidationException) {
        }

        app(ShiftService::class)->close($this->shift, ['1000' => 1]);
        $z = app(ZReadingService::class)->generate($this->terminal, $this->supervisor);

        $this->assertSame(3, $z->data['invoice_count']);
        $this->assertSame('371.43', $z->data['net_sales']);
        $this->assertSame('17.86', $z->data['senior_discounts']);
        $this->assertSame('100.00', $z->data['voids']);
        $this->assertSame('271.43', $z->data['net_after_adjustments']);
        $this->assertSame('0.00', $z->grand_total_before);
        $this->assertSame('371.43', $z->grand_total_after);
        $this->assertSame(1, $z->z_counter);

        // A sale already in a Z-reading cannot be voided.
        $sale = Sale::where('status', 'completed')->firstOrFail();
        $newShift = app(ShiftService::class)->open($this->cashier, $this->terminal, '0');
        $this->expectException(ValidationException::class);
        app(SaleAdjustmentService::class)->void($sale, $newShift, $this->cashier, 'Late', 'pharma', '2468');
        unset($z);
        $this->assertSame(1, Reading::count());
    }

    public function test_a_terminal_has_only_one_open_shift()
    {
        $other = User::factory()->create(['branch_id' => $this->cashier->branch_id])->assignRole(Role::Cashier);

        $this->expectException(ValidationException::class);
        app(ShiftService::class)->open($other, $this->terminal, '500');
    }
}
