<?php

namespace Tests\Feature\Compliance;

use App\Enums\Role;
use App\Models\Prescriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickPrescriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_cashier_adds_a_missing_prescriber_from_the_pos()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)
            ->postJson(route('pos.rx.prescribers.store'), ['name' => 'Dr. Liza Ramos', 'prc_license_number' => '0099887'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Dr. Liza Ramos');

        $this->assertTrue(Prescriber::where('prc_license_number', '0099887')->exists());
        $this->assertDatabaseHas('activity_log', ['subject_type' => (new Prescriber)->getMorphClass(), 'event' => 'created']);
    }

    public function test_the_prc_number_must_be_unique()
    {
        Prescriber::factory()->create(['prc_license_number' => '0099887']);
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)
            ->postJson(route('pos.rx.prescribers.store'), ['name' => 'Someone Else', 'prc_license_number' => '0099887'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prc_license_number');
    }

    public function test_staff_who_neither_sell_nor_dispense_cannot_add_prescribers_here()
    {
        $clerk = User::factory()->create()->assignRole(Role::InventoryClerk);

        $this->actingAs($clerk)->postJson(route('pos.rx.prescribers.store'), ['name' => 'Dr. X'])->assertForbidden();
    }
}
