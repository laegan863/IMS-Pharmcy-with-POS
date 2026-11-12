<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\Product;
use App\Models\Terminal;
use App\Models\User;
use App\Notifications\DailySummary;
use App\Reports\ReportRegistry;
use App\Services\Sales\SaleService;
use App\Services\Sales\ShiftService;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);
        $this->cashier = User::factory()->create(['branch_id' => $this->owner->branch_id])->assignRole(Role::Cashier);

        $product = Product::factory()->create(['reorder_point' => 100]);
        $product->units()->where('name', 'Tablet')->update(['price' => '11.20']);
        app(StockService::class)->receive($this->owner->branch_id, $product, 'L1', now()->addDays(20)->toDateString(), 50, '5.00');

        $terminal = Terminal::factory()->create(['branch_id' => $this->owner->branch_id]);
        $shift = app(ShiftService::class)->open($this->cashier, $terminal, '0');
        app(SaleService::class)->complete([
            'client_uuid' => (string) Str::uuid(),
            'lines' => [['product_id' => $product->id, 'product_unit_id' => $product->units()->where('name', 'Tablet')->value('id'), 'quantity' => 10]],
            'discount_type' => 'none',
            'payments' => [['method' => 'cash', 'amount' => '200']],
        ], $this->cashier, $shift);
    }

    public function test_every_report_renders_for_the_owner()
    {
        foreach (array_keys(ReportRegistry::all()) as $key) {
            $this->actingAs($this->owner)->get(route('reports.show', $key))->assertOk();
        }
    }

    public function test_gross_profit_excludes_vat_and_uses_batch_cost()
    {
        $this->actingAs($this->owner)
            ->get(route('reports.show', 'gross-profit'))
            ->assertInertia(fn (Assert $page) => $page
                // 112.00 sold, 100.00 ex-VAT, cost 10 × 5.00 = 50.00.
                ->where('rows.0.net', '112.00')
                ->where('rows.0.net_ex_vat', '100.00')
                ->where('rows.0.cost', '50.00')
                ->where('rows.0.profit', '50.00')
                ->where('rows.0.margin', '50.0'));
    }

    public function test_sales_by_day_and_valuation()
    {
        $this->actingAs($this->owner)
            ->get(route('reports.show', 'sales-by-day'))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.net', '112.00')->where('rows.0.invoices', 1));

        $this->actingAs($this->owner)
            ->get(route('reports.show', 'inventory-valuation'))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.on_hand', 40)->where('rows.0.value', '200.00'));
    }

    public function test_cashiers_cannot_see_profit()
    {
        $this->actingAs($this->cashier)->get(route('reports.show', 'gross-profit'))->assertForbidden();
    }

    public function test_the_dashboard_shows_branch_figures_to_the_owner_and_own_sales_to_a_cashier()
    {
        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.scope', 'branch')
                ->where('dashboard.today.net', '112.00')
                ->has('dashboard.week', 7)
                ->has('dashboard.near_expiry', 1)
                ->has('dashboard.low_stock', 1));

        $this->actingAs($this->cashier)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.scope', 'mine')
                ->where('dashboard.today.invoices', 1)
                ->missing('dashboard.low_stock'));
    }

    public function test_the_daily_summary_is_emailed_to_owners()
    {
        Notification::fake();

        $this->artisan('reports:daily-summary', ['--date' => now()->toDateString()])->assertSuccessful();

        Notification::assertSentTo($this->owner, DailySummary::class, fn (DailySummary $summary) => $summary->branches[0]['net'] === '112.00');
        Notification::assertNotSentTo($this->cashier, DailySummary::class);
    }
}
