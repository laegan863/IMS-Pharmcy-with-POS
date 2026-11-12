<?php

namespace Tests\Feature\Sales;

use App\Enums\Role;
use App\Models\HeldSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Terminal $terminal;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cashier = User::factory()->create()->assignRole(Role::Cashier);
        $this->terminal = Terminal::factory()->create(['branch_id' => $this->cashier->branch_id]);
        $this->product = Product::factory()->create();
        app(StockService::class)->receive($this->cashier->branch_id, $this->product, 'L1', now()->addYear()->toDateString(), 100, '3.00');
    }

    private function tabletId(): int
    {
        return $this->product->units()->where('name', 'Tablet')->value('id');
    }

    private function openShift(): void
    {
        $this->actingAs($this->cashier)->post(route('pos.shifts.store'), [
            'terminal_id' => $this->terminal->id,
            'opening_cash' => '500',
        ])->assertRedirect(route('pos.screen'));
    }

    public function test_the_pos_asks_for_a_shift_first()
    {
        $this->actingAs($this->cashier)->get(route('pos.screen'))->assertRedirect(route('pos.shifts.create'));
    }

    public function test_a_cashier_opens_a_shift_and_sees_the_sale_screen()
    {
        $this->openShift();

        $this->actingAs($this->cashier)
            ->get(route('pos.screen'))
            ->assertInertia(fn (Assert $page) => $page->component('pos/terminal')->where('idleTimeoutMinutes', 15));
    }

    public function test_quote_prices_on_the_server()
    {
        $this->openShift();

        $this->actingAs($this->cashier)
            ->postJson(route('pos.quote'), [
                'discount_type' => 'none',
                'lines' => [['product_id' => $this->product->id, 'product_unit_id' => $this->tabletId(), 'quantity' => 3, 'unit_price' => null]],
            ])
            ->assertOk()
            ->assertJsonPath('totals.line_total', '15.00')
            ->assertJsonPath('lines.0.available', 100);
    }

    public function test_completing_a_sale_and_printing_then_reprinting()
    {
        $this->openShift();
        $uuid = (string) Str::uuid();
        $payload = [
            'client_uuid' => $uuid,
            'discount_type' => 'none',
            'lines' => [['product_id' => $this->product->id, 'product_unit_id' => $this->tabletId(), 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => '20']],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('pos.sales.complete'), $payload)->assertOk()->assertJsonPath('change_due', '10.00');
        $this->actingAs($this->cashier)->postJson(route('pos.sales.complete'), $payload)->assertOk()->assertJsonPath('id', $response->json('id'));
        $this->assertSame(1, Sale::count());

        $sale = Sale::sole();
        $this->actingAs($this->cashier)->get(route('pos.sales.receipt', $sale))->assertOk()->assertSee('SALES INVOICE')->assertDontSee('REPRINT');
        $this->actingAs($this->cashier)->get(route('pos.sales.receipt', $sale))->assertOk()->assertSee('REPRINT');
        $this->assertSame(1, $sale->fresh()?->reprint_count);
    }

    public function test_validation_errors_come_back_as_json()
    {
        $this->openShift();

        $this->actingAs($this->cashier)->postJson(route('pos.sales.complete'), [
            'client_uuid' => (string) Str::uuid(),
            'discount_type' => 'none',
            'lines' => [['product_id' => $this->product->id, 'product_unit_id' => $this->tabletId(), 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => '1']],
        ])->assertUnprocessable()->assertJsonValidationErrors('payments');
    }

    public function test_hold_and_resume_a_cart()
    {
        $this->openShift();

        $this->actingAs($this->cashier)->postJson(route('pos.held.store'), ['label' => 'Mrs. Cruz', 'cart' => ['lines' => [['product_id' => 1]]]])->assertCreated();
        $held = HeldSale::sole();

        $this->actingAs($this->cashier)->getJson(route('pos.held.index'))->assertJsonPath('data.0.label', 'Mrs. Cruz');
        $this->actingAs($this->cashier)->postJson(route('pos.held.resume', $held))->assertJsonPath('cart.lines.0.product_id', 1);
        $this->assertSame(0, HeldSale::count());
    }

    public function test_closing_the_shift_shows_the_x_reading()
    {
        $this->openShift();

        $response = $this->actingAs($this->cashier)->post(route('pos.shifts.close'), ['denominations' => ['500' => 1]]);
        $response->assertRedirect();

        $this->actingAs($this->cashier)->get((string) $response->headers->get('Location'))->assertOk()->assertSee('X-READING');
    }

    public function test_cashiers_cannot_run_z_readings()
    {
        $this->actingAs($this->cashier)->get(route('pos.readings.index'))->assertForbidden();
    }

    public function test_inventory_clerks_cannot_sell()
    {
        $clerk = User::factory()->create()->assignRole(Role::InventoryClerk);

        $this->actingAs($clerk)->get(route('pos.screen'))->assertForbidden();
    }
}
