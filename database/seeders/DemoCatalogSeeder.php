<?php

namespace Database\Seeders;

use App\Enums\ProductClassification;
use App\Enums\TaxClass;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DosageForm;
use App\Models\Prescriber;
use App\Models\Supplier;
use App\Services\Catalog\ProductService;
use Illuminate\Database\Seeder;

/**
 * A small, realistic catalog for local development and demos. Not for
 * production: the store's real list is loaded with the product import.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(ProductService $products): void
    {
        $tablet = DosageForm::findOrCreateByName('Tablet');
        $capsule = DosageForm::findOrCreateByName('Capsule');
        $syrup = DosageForm::findOrCreateByName('Syrup');
        DosageForm::findOrCreateByName('Cream');

        $analgesics = Category::findOrCreateByName('Analgesics');
        $antibiotics = Category::findOrCreateByName('Antibiotics');
        $vitamins = Category::findOrCreateByName('Vitamins');
        $personalCare = Category::findOrCreateByName('Personal care');

        $catalog = [
            ['BIO-500', 'Paracetamol', 'Biogesic', '500 mg', $tablet, 'Unilab', $analgesics, ProductClassification::Otc, TaxClass::Vatable, [['Tablet', 1, '5.50', '4800010000011'], ['Blister', 10, '52.00', null], ['Box', 100, '500.00', '4800010000035']]],
            ['ALX-500', 'Mefenamic acid', 'Dolfenal', '500 mg', $capsule, 'Unilab', $analgesics, ProductClassification::Otc, TaxClass::Vatable, [['Capsule', 1, '18.00', '4800010000042'], ['Box', 100, '1700.00', null]]],
            ['AMX-500', 'Amoxicillin', 'Amoxil', '500 mg', $capsule, 'GSK', $antibiotics, ProductClassification::Rx, TaxClass::VatExempt, [['Capsule', 1, '15.00', '4800010000059'], ['Box', 100, '1400.00', null]]],
            ['MET-500', 'Metformin', 'Glucophage', '500 mg', $tablet, 'Merck', null, ProductClassification::Rx, TaxClass::VatExempt, [['Tablet', 1, '9.00', '4800010000066'], ['Box', 100, '850.00', null]]],
            ['ASC-500', 'Ascorbic acid', 'Ceelin', '100 mg/5 mL', $syrup, 'Unilab', $vitamins, ProductClassification::Otc, TaxClass::Vatable, [['Bottle', 1, '165.00', '4800010000073']]],
            ['ALC-500', 'Isopropyl alcohol', 'Green Cross', '70%', null, 'Green Cross', $personalCare, ProductClassification::NonDrug, TaxClass::Vatable, [['Bottle', 1, '89.00', '4800010000080']]],
        ];

        foreach ($catalog as [$sku, $generic, $brand, $strength, $form, $maker, $category, $class, $tax, $units]) {
            $products->save(null, [
                'sku' => $sku,
                'generic_name' => $generic,
                'brand_name' => $brand,
                'strength' => $strength,
                'dosage_form_id' => $form?->id,
                'manufacturer' => $maker,
                'category_id' => $category?->id,
                'classification' => $class->value,
                'tax_class' => $tax->value,
                'statutory_discount_eligible' => $class !== ProductClassification::NonDrug,
                'storage_condition' => 'room',
                'reorder_point' => 100,
                'is_active' => true,
            ], array_map(fn (array $unit) => [
                'name' => $unit[0],
                'conversion_factor' => $unit[1],
                'is_base' => $unit[1] === 1,
                'is_sellable' => true,
                'is_purchasable' => $unit[1] > 1 || count($units) === 1,
                'price' => $unit[2],
            ], $units), array_values(array_filter(array_map(
                fn (array $unit) => $unit[3] ? ['code' => $unit[3], 'unit' => $unit[0]] : null,
                $units,
            ))), true);
        }

        Supplier::factory()->create(['code' => 'ZUEL', 'name' => 'Zuellig Pharma Corporation', 'payment_terms_days' => 30]);
        Supplier::factory()->create(['code' => 'METRO', 'name' => 'Metro Drug Inc.', 'payment_terms_days' => 45]);

        Customer::factory()->senior()->create(['name' => 'Remedios Santos', 'senior_citizen_id' => 'SC-2019-004512']);
        Customer::factory()->pwd()->create(['name' => 'Paolo Reyes', 'pwd_id' => 'PWD-13-7654321']);

        Prescriber::factory()->create(['name' => 'Dr. Ana Villanueva', 'specialty' => 'Internal Medicine']);
        Prescriber::factory()->withS2()->create(['name' => 'Dr. Ramon Cruz', 'specialty' => 'Psychiatry']);
    }
}
