<?php

namespace Tests\Feature\Catalog;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductPriceChange;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'BIO-500',
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Biogesic',
            'strength' => '500 mg',
            'manufacturer' => 'Unilab',
            'classification' => 'otc',
            'tax_class' => 'vatable',
            'statutory_discount_eligible' => true,
            'storage_condition' => 'room',
            'reorder_point' => 200,
            'is_active' => true,
            'units' => [
                ['name' => 'Tablet', 'conversion_factor' => 1, 'is_base' => true, 'is_sellable' => true, 'is_purchasable' => false, 'price' => '5.50'],
                ['name' => 'Blister', 'conversion_factor' => 10, 'is_base' => false, 'is_sellable' => true, 'is_purchasable' => false, 'price' => '52'],
                ['name' => 'Box', 'conversion_factor' => 100, 'is_base' => false, 'is_sellable' => true, 'is_purchasable' => true, 'price' => '500'],
            ],
            'barcodes' => [
                ['code' => '4800010000011', 'unit' => 'Tablet'],
                ['code' => '4800010000035', 'unit' => 'box'],
            ],
        ], $overrides);
    }

    public function test_a_product_is_created_with_units_barcodes_and_lookups()
    {
        $this->actingAs($this->owner)
            ->post(route('catalog.products.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $product = Product::with(['units', 'barcodes.unit', 'genericName', 'manufacturer'])->where('sku', 'BIO-500')->sole();

        $this->assertSame('Paracetamol', $product->genericName->name);
        $this->assertSame('Unilab', $product->manufacturer?->name);
        $this->assertSame([1, 10, 100], $product->units->pluck('conversion_factor')->all());
        $this->assertSame('Tablet', $product->baseUnit?->name);
        $this->assertSame('500.00', $product->units->firstWhere('name', 'Box')?->price);
        $this->assertSame('Box', $product->barcodes->firstWhere('code', '4800010000035')?->unit->name);
        $this->assertSame(3, ProductPriceChange::count());
    }

    public function test_exactly_one_base_unit_with_factor_one_is_required()
    {
        $payload = $this->payload();
        $payload['units'][1]['is_base'] = true;

        $this->actingAs($this->owner)
            ->post(route('catalog.products.store'), $payload)
            ->assertSessionHasErrors('units');

        $payload = $this->payload();
        $payload['units'][0]['conversion_factor'] = 2;

        $this->actingAs($this->owner)
            ->post(route('catalog.products.store'), $payload)
            ->assertSessionHasErrors('units.0.conversion_factor');
    }

    public function test_a_barcode_cannot_belong_to_two_products()
    {
        Product::factory()->create()->barcodes()->create([
            'code' => '4800010000011',
            'product_unit_id' => Product::query()->firstOrFail()->units()->firstOrFail()->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('catalog.products.store'), $this->payload())
            ->assertSessionHasErrors('barcodes.0.code');
    }

    public function test_price_changes_are_recorded_with_who_changed_them()
    {
        $this->actingAs($this->owner)->post(route('catalog.products.store'), $this->payload());
        $product = Product::with('units')->where('sku', 'BIO-500')->sole();

        $payload = $this->payload();
        $payload['units'] = $product->units->map(fn ($unit) => [
            ...$unit->only(['id', 'name', 'conversion_factor', 'is_base', 'is_sellable', 'is_purchasable']),
            'price' => $unit->name === 'Box' ? '520.00' : $unit->price,
        ])->all();

        $this->actingAs($this->owner)
            ->put(route('catalog.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $change = ProductPriceChange::query()->latest('id')->firstOrFail();
        $this->assertSame('500.00', $change->old_price);
        $this->assertSame('520.00', $change->new_price);
        $this->assertSame($this->owner->id, $change->changed_by);
    }

    public function test_a_clerk_without_price_permission_cannot_change_prices()
    {
        $clerk = User::factory()->create()->assignRole(Role::InventoryClerk);
        $product = Product::factory()->create(['sku' => 'BIO-500']);
        $product->load('units');

        $payload = $this->payload(['barcodes' => []]);
        $payload['units'] = $product->units->map(fn ($unit) => [
            ...$unit->only(['id', 'name', 'conversion_factor', 'is_base', 'is_sellable', 'is_purchasable']),
            'price' => '1.00',
        ])->all();

        $this->actingAs($clerk)
            ->put(route('catalog.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(['5.00', '450.00'], $product->units()->pluck('price')->all());
    }

    public function test_a_cashier_can_list_but_not_edit_products()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)->get(route('catalog.products.index'))->assertOk();
        $this->actingAs($cashier)->get(route('catalog.products.create'))->assertForbidden();
    }

    public function test_the_list_searches_by_generic_name()
    {
        $this->actingAs($this->owner)->post(route('catalog.products.store'), $this->payload());
        Product::factory()->count(3)->create();

        $this->actingAs($this->owner)
            ->get(route('catalog.products.index', ['search' => 'paracet']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.sku', 'BIO-500'));
    }

    public function test_lookup_finds_by_barcode_with_the_scanned_unit()
    {
        $this->actingAs($this->owner)->post(route('catalog.products.store'), $this->payload());
        $box = Product::where('sku', 'BIO-500')->sole()->units()->where('name', 'Box')->sole();

        $this->actingAs($this->owner)
            ->getJson(route('catalog.lookup.products', ['q' => '4800010000035']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.scanned_unit_id', $box->id)
            ->assertJsonPath('data.0.units.2.conversion_factor', 100);
    }

    public function test_lookup_finds_by_brand_or_generic_name()
    {
        $this->actingAs($this->owner)->post(route('catalog.products.store'), $this->payload());

        foreach (['biog', 'Paracetamol', 'BIO-500'] as $term) {
            $this->actingAs($this->owner)
                ->getJson(route('catalog.lookup.products', ['q' => $term]))
                ->assertJsonPath('data.0.sku', 'BIO-500');
        }
    }
}
