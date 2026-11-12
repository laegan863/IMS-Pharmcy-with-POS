<?php

namespace Tests\Feature\Stock;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $clerk;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clerk = User::factory()->create()->assignRole(Role::InventoryClerk);
        $this->product = Product::factory()->create(['sku' => 'BIO-500', 'reorder_point' => 100]);
    }

    public function test_opening_stock_import_converts_units_and_costs()
    {
        $csv = "sku,lot_number,expires_on,unit,quantity,unit_cost\n"
            ."BIO-500,L1,2027-06-30,Box,3,450.00\n"
            ."BIO-500,L2,2027-11-30,,35,4.50\n";

        $this->actingAs($this->clerk)
            ->post(route('stock.opening.store'), ['file' => UploadedFile::fake()->createWithContent('opening.csv', $csv)])
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.errors', [])
                ->where('report.batches', 2)
                ->where('report.units', 335));

        $this->assertSame(335, app(StockService::class)->onHand($this->clerk->branch_id, $this->product->id));
        $this->assertSame('4.5000', Batch::where('lot_number', 'L1')->sole()->unit_cost);
        $this->assertSame(2, DB::table('stock_movements')->where('type', 'opening')->count());
    }

    public function test_opening_stock_with_an_unknown_sku_imports_nothing()
    {
        $csv = "sku,lot_number,expires_on,unit,quantity,unit_cost\n"
            ."BIO-500,L1,2027-06-30,,10,4.50\n"
            ."NOPE,L2,2027-06-30,,10,4.50\n"
            ."BIO-500,L3,,Carton,10,4.50\n";

        $this->actingAs($this->clerk)
            ->post(route('stock.opening.store'), ['file' => UploadedFile::fake()->createWithContent('opening.csv', $csv)])
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.batches', 0)
                ->where('report.errors', fn ($errors) => collect($errors)->pluck('row')->unique()->values()->all() === [3, 4]));

        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_on_hand_lists_only_the_users_branch_and_flags_low_stock()
    {
        $stock = app(StockService::class);
        $stock->receive($this->clerk->branch_id, $this->product, 'L1', now()->addYear()->toDateString(), 40, '4.00');
        $stock->receive(Branch::factory()->create()->id, $this->product, 'L1', now()->addYear()->toDateString(), 500, '4.00');

        $this->actingAs($this->clerk)
            ->get(route('stock.index', ['filter' => ['view' => 'low']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.on_hand', 40)
                ->where('rows.data.0.sellable', 40)
                ->where('rows.data.0.value', '160.00'));
    }

    public function test_near_expiry_filter_and_batch_history()
    {
        $stock = app(StockService::class);
        $stock->receive($this->clerk->branch_id, $this->product, 'SOON', now()->addDays(20)->toDateString(), 10, '4.00');
        $stock->issue($this->clerk->branch_id, $this->product, 3, StockMovementType::Sale);
        Product::factory()->create();

        $this->actingAs($this->clerk)
            ->get(route('stock.index', ['filter' => ['view' => 'near_expiry']]))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));

        $batch = Batch::sole();

        $this->actingAs($this->clerk)
            ->get(route('stock.batch', $batch))
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements', 2)
                ->where('movements.0.quantity', -3)
                ->where('movements.0.balance', 7));
    }

    public function test_cashiers_cannot_import_opening_stock()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)->get(route('stock.opening'))->assertForbidden();
        $this->actingAs($cashier)->get(route('stock.index'))->assertOk();
    }

    public function test_the_nightly_check_passes_and_catches_tampering()
    {
        app(StockService::class)->receive($this->clerk->branch_id, $this->product, 'L1', now()->addYear()->toDateString(), 10, '4.00');

        $this->artisan('stock:verify-ledger')->assertSuccessful();

        DB::table('stock_balances')->update(['quantity' => 9]);

        $this->artisan('stock:verify-ledger')->assertFailed();
        $this->assertDatabaseHas('activity_log', ['event' => 'ledger-mismatch']);
    }
}
