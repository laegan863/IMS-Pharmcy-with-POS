<?php

namespace Tests\Feature\Compliance;

use App\Enums\ProductClassification;
use App\Enums\Role;
use App\Models\DangerousDrugEntry;
use App\Models\PrescriptionLine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Compliance\PrescriptionService;
use App\Services\Sales\SaleAdjustmentService;
use App\Services\Sales\SaleService;
use App\Services\Sales\ShiftService;
use App\Services\Stock\StockService;
use App\Settings\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $pharmacist;

    private Shift $shift;

    private Product $amoxil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cashier = User::factory()->create()->assignRole(Role::Cashier);
        $this->pharmacist = User::factory()->withTwoFactor()->create([
            'branch_id' => $this->cashier->branch_id,
            'username' => 'pharma',
            'supervisor_pin' => Hash::make('2468'),
        ])->assignRole(Role::Pharmacist);
        $terminal = Terminal::factory()->create(['branch_id' => $this->cashier->branch_id]);
        $this->shift = app(ShiftService::class)->open($this->cashier, $terminal, '0');
        $this->amoxil = Product::factory()->rx()->create();
        app(StockService::class)->receive($this->cashier->branch_id, $this->amoxil, 'A1', now()->addYear()->toDateString(), 100, '3.00');
    }

    private function tablet(Product $product): int
    {
        return $product->units()->where('name', 'Tablet')->value('id');
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $extra
     */
    private function sell(Product $product, int $quantity, array $line = [], array $extra = [], ?User $as = null, ?Shift $shift = null): Sale
    {
        return app(SaleService::class)->complete([
            'client_uuid' => (string) Str::uuid(),
            'lines' => [['product_id' => $product->id, 'product_unit_id' => $this->tablet($product), 'quantity' => $quantity, ...$line]],
            'discount_type' => 'none',
            'payments' => [['method' => 'cash', 'amount' => '10000']],
            ...$extra,
        ], $as ?? $this->cashier, $shift ?? $this->shift);
    }

    private function prescription(Product $product, int $quantity, ?string $serial = null, ?string $s2Number = null): int
    {
        $prescription = app(PrescriptionService::class)->create($this->cashier->branch_id, [
            'patient_name' => 'Juan Dela Cruz',
            'prescriber_name' => 'Dr. Liza Ramos',
            'prescriber_s2_number' => $s2Number,
            'prescribed_on' => now()->toDateString(),
            'special_form_serial' => $serial,
        ], [['product_id' => $product->id, 'quantity_prescribed_base' => $quantity]], $this->cashier);

        return $prescription->lines->firstOrFail()->id;
    }

    public function test_rx_items_need_a_prescription()
    {
        $this->expectException(ValidationException::class);
        $this->sell($this->amoxil, 2);
    }

    public function test_rx_items_can_be_sold_without_one_when_the_block_is_off()
    {
        app(Settings::class)->set(['prescription.block_rx_without_prescription' => false]);

        $this->assertSame('completed', $this->sell($this->amoxil, 2)->status);
    }

    public function test_partial_fills_track_the_balance()
    {
        $lineId = $this->prescription($this->amoxil, 21);

        $this->sell($this->amoxil, 14, ['prescription_line_id' => $lineId]);
        $this->assertSame(7, PrescriptionLine::findOrFail($lineId)->balance());

        try {
            $this->sell($this->amoxil, 8, ['prescription_line_id' => $lineId]);
            $this->fail('Overfill accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Only 7 left', implode(' ', $exception->errors()['lines.0.quantity']));
        }

        $this->sell($this->amoxil, 7, ['prescription_line_id' => $lineId]);
        $this->assertSame(0, PrescriptionLine::findOrFail($lineId)->balance());
    }

    public function test_a_generic_equivalent_can_fill_the_prescription()
    {
        $generic = Product::factory()->rx()->create(['generic_name_id' => $this->amoxil->generic_name_id, 'brand_name' => null]);
        app(StockService::class)->receive($this->cashier->branch_id, $generic, 'G1', now()->addYear()->toDateString(), 50, '1.00');
        $lineId = $this->prescription($this->amoxil, 10);

        $this->assertSame('completed', $this->sell($generic, 10, ['prescription_line_id' => $lineId])->status);
    }

    public function test_a_refund_gives_the_prescription_back_its_balance()
    {
        $lineId = $this->prescription($this->amoxil, 10);
        $sale = $this->sell($this->amoxil, 10, ['prescription_line_id' => $lineId]);

        app(SaleAdjustmentService::class)->refund($sale, $this->shift, $this->cashier, [$sale->lines()->sole()->id => 4], 'Allergy', 'pharma', '2468');

        $this->assertSame(4, PrescriptionLine::findOrFail($lineId)->balance());
    }

    public function test_dangerous_drugs_need_s2_form_and_a_pharmacist_and_are_registered()
    {
        $drug = Product::factory()->create(['classification' => ProductClassification::DangerousDrug]);
        app(StockService::class)->receive($this->cashier->branch_id, $drug, 'D1', now()->addYear()->toDateString(), 30, '10.00');

        try {
            $this->prescription($drug, 10, null, 'S2-7788');
            $this->fail('Dangerous drug prescription without form serial accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('special_form_serial', $exception->errors());
        }

        try {
            $this->prescription($drug, 10, 'SPF-001');
            $this->fail('Prescriber without S2 number accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('prescriber_s2_number', $exception->errors());
        }

        $lineId = $this->prescription($drug, 10, 'SPF-001', 'S2-7788');

        try {
            $this->sell($drug, 10, ['prescription_line_id' => $lineId]);
            $this->fail('Cashier dispensed a dangerous drug without a pharmacist.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approver_pin', $exception->errors());
        }

        $sale = $this->sell($drug, 10, ['prescription_line_id' => $lineId], ['approver' => 'pharma', 'approver_pin' => '2468']);

        $entry = DangerousDrugEntry::sole();
        $this->assertSame('SPF-001', $entry->special_form_serial);
        $this->assertSame('Dr. Liza Ramos', $entry->prescriber_name);
        $this->assertSame('S2-7788', $entry->prescriber_s2_number);
        $this->assertSame(20, $entry->balance_after);
        $this->assertSame($this->pharmacist->id, $entry->pharmacist_id);

        $this->expectException(ValidationException::class);
        app(SaleAdjustmentService::class)->void($sale, $this->shift, $this->cashier, 'Mistake', 'pharma', '2468');
    }

    public function test_the_pos_lists_generic_alternatives_cheapest_first()
    {
        $cheap = Product::factory()->rx()->create(['generic_name_id' => $this->amoxil->generic_name_id, 'brand_name' => 'Amoxicap']);
        $cheap->units()->where('is_base', true)->update(['price' => '2.00']);

        $this->actingAs($this->cashier)
            ->getJson(route('pos.rx.alternatives', ['product_id' => $this->amoxil->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $cheap->id)
            ->assertJsonPath('data.0.base_price', '2.00');
    }

    public function test_the_pos_records_a_quick_prescription()
    {
        $this->actingAs($this->cashier)
            ->postJson(route('pos.rx.store'), [
                'patient_name' => 'Maria Santos',
                'prescriber_name' => 'Dr. Liza Ramos',
                'prescribed_on' => now()->toDateString(),
                'product_id' => $this->amoxil->id,
                'quantity_prescribed_base' => 21,
            ])
            ->assertCreated()
            ->assertJsonStructure(['prescription_line_id', 'number']);

        $this->actingAs($this->cashier)
            ->getJson(route('pos.rx.open-lines', ['product_id' => $this->amoxil->id]))
            ->assertJsonPath('data.0.balance', 21);
    }

    public function test_compliance_reports_render_export_and_log_personal_data_access()
    {
        app(Settings::class)->set(['prescription.block_rx_without_prescription' => false]);
        $this->sell($this->amoxil, 1, [], ['discount_type' => 'senior', 'discount_id_number' => 'SC-77', 'discount_holder_name' => 'Lola']);

        $this->actingAs($this->pharmacist)
            ->get(route('reports.show', 'senior-book'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/show')
                ->has('rows', 1)
                ->where('rows.0.id_number', 'SC-77'));

        $this->assertDatabaseHas('activity_log', ['log_name' => 'privacy', 'event' => 'report-viewed']);

        foreach (['sales-journal', 'voids-refunds', 'prescription-book', 'dangerous-drug-register', 'generic-price-list', 'bir-sales-summary', 'pwd-book'] as $key) {
            $this->actingAs($this->pharmacist)->get(route('reports.show', $key))->assertOk();
        }

        $this->actingAs($this->pharmacist)->get(route('reports.excel', 'sales-journal'))->assertOk()->assertDownload();
        $this->actingAs($this->pharmacist)->get(route('reports.pdf', 'sales-journal'))->assertOk();
    }

    public function test_cashiers_cannot_open_compliance_reports()
    {
        $this->actingAs($this->cashier)->get(route('reports.show', 'senior-book'))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('reports.show', 'nope'))->assertNotFound();
    }
}
