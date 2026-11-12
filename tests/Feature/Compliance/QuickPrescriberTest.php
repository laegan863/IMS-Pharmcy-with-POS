<?php

namespace Tests\Feature\Compliance;

use App\Enums\Role;
use App\Models\Prescriber;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The prescriber on a prescription is free text: any doctor can be entered,
 * and doctors on the Prescribers list are only offered as suggestions.
 */
class QuickPrescriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_the_pos_records_a_prescription_from_a_doctor_not_on_file()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);
        $product = Product::factory()->rx()->create();

        $this->actingAs($cashier)
            ->postJson(route('pos.rx.store'), [
                'patient_name' => 'Maria Santos',
                'prescriber_name' => 'Dr. Liza Ramos',
                'prescriber_prc_number' => '0099887',
                'prescribed_on' => now()->toDateString(),
                'product_id' => $product->id,
                'quantity_prescribed_base' => 21,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('prescriptions', ['prescriber_name' => 'Dr. Liza Ramos', 'prescriber_prc_number' => '0099887']);
        $this->assertSame(0, Prescriber::count());
        $this->assertSame('Dr. Liza Ramos', Prescription::sole()->prescriber_name);
    }

    public function test_the_prescriber_name_is_required()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);
        $product = Product::factory()->rx()->create();

        $this->actingAs($cashier)
            ->postJson(route('pos.rx.store'), [
                'patient_name' => 'Maria Santos',
                'prescribed_on' => now()->toDateString(),
                'product_id' => $product->id,
                'quantity_prescribed_base' => 21,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prescriber_name');
    }

    public function test_doctors_on_file_are_suggested_while_typing()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);
        Prescriber::factory()->withS2()->create(['name' => 'Dr. Ramon Cruz']);

        $this->actingAs($cashier)
            ->getJson(route('pos.rx.prescribers', ['q' => 'Ramon']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Dr. Ramon Cruz');
    }
}
