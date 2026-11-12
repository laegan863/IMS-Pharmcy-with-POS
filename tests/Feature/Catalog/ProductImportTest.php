<?php

namespace Tests\Feature\Catalog;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductImportTest extends TestCase
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
     * @param  list<list<string|int>>  $rows
     */
    private function csv(array $rows): UploadedFile
    {
        $header = 'sku,generic_name,brand_name,strength,dosage_form,manufacturer,category,classification,tax_class,fda_registration_number,storage_condition,reorder_point,base_unit,base_price,base_barcode,unit2_name,unit2_factor,unit2_price,unit2_barcode,unit3_name,unit3_factor,unit3_price,unit3_barcode';
        $lines = array_map(fn (array $row) => implode(',', $row), $rows);

        return UploadedFile::fake()->createWithContent('products.csv', $header."\n".implode("\n", $lines)."\n");
    }

    public function test_a_valid_file_imports_products_with_units()
    {
        $file = $this->csv([
            ['BIO-500', 'Paracetamol', 'Biogesic', '500 mg', 'Tablet', 'Unilab', 'Analgesics', 'OTC', 'VATable', '', 'room', 200, 'Tablet', '5.50', '111', 'Box', 100, '500', '222', '', '', '', ''],
            ['AMX-500', 'Amoxicillin', 'Amoxil', '500 mg', 'Capsule', '', 'Antibiotics', 'rx', 'VAT-exempt', '', '', 50, 'Capsule', '8', '', '', '', '', '', '', '', '', ''],
        ]);

        $this->actingAs($this->owner)
            ->post(route('catalog.products.import.store'), ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.created', 2)
                ->where('report.errors', []));

        $amoxil = Product::with('units')->where('sku', 'AMX-500')->sole();
        $this->assertSame('vat_exempt', $amoxil->tax_class->value);
        $this->assertSame('rx', $amoxil->classification->value);
        $this->assertSame([1, 100], Product::where('sku', 'BIO-500')->sole()->units()->pluck('conversion_factor')->all());
    }

    public function test_an_invalid_row_stops_the_whole_import_and_is_reported()
    {
        $file = $this->csv([
            ['BIO-500', 'Paracetamol', '', '', '', '', '', 'otc', 'vatable', '', '', '', 'Tablet', '5', '', '', '', '', '', '', '', '', ''],
            ['BAD-1', '', '', '', '', '', '', 'candy', 'vatable', '', '', '', 'Tablet', '-1', '', '', '', '', '', '', '', '', ''],
            ['BIO-500', 'Paracetamol', '', '', '', '', '', 'otc', 'vatable', '', '', '', 'Tablet', '5', '', '', '', '', '', '', '', '', ''],
        ]);

        $this->actingAs($this->owner)
            ->post(route('catalog.products.import.store'), ['file' => $file])
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.created', 0)
                ->where('report.errors', fn ($errors) => collect($errors)->contains(fn ($error) => $error['row'] === 3 && $error['column'] === 'generic_name')
                    && collect($errors)->contains(fn ($error) => $error['row'] === 3 && $error['column'] === 'classification')
                    && collect($errors)->contains(fn ($error) => $error['row'] === 4 && $error['column'] === 'sku')));

        $this->assertSame(0, Product::count());
    }

    public function test_an_existing_sku_is_updated()
    {
        Product::factory()->create(['sku' => 'BIO-500']);

        $file = $this->csv([
            ['BIO-500', 'Paracetamol', 'Biogesic', '', '', '', '', 'otc', 'vatable', '', '', '', 'Tablet', '6', '', 'Box', 100, '550', '', '', '', '', ''],
        ]);

        $this->actingAs($this->owner)
            ->post(route('catalog.products.import.store'), ['file' => $file])
            ->assertInertia(fn (Assert $page) => $page->where('report.updated', 1));

        $this->assertSame(['6.00', '550.00'], Product::where('sku', 'BIO-500')->sole()->units()->pluck('price')->all());
    }

    public function test_the_template_downloads()
    {
        $this->actingAs($this->owner)
            ->get(route('catalog.products.import.template'))
            ->assertOk()
            ->assertDownload('product-import-template.xlsx');
    }
}
