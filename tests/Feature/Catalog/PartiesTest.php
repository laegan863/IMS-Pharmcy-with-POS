<?php

namespace Tests\Feature\Catalog;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartiesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);
    }

    public function test_customer_id_numbers_are_encrypted_masked_and_searchable()
    {
        $this->actingAs($this->owner)->post(route('catalog.customers.store'), [
            'name' => 'Lola Remedios',
            'senior_citizen_id' => 'SC-2024-001234',
        ])->assertSessionHasNoErrors();

        $raw = DB::table('customers')->value('senior_citizen_id');
        $this->assertStringNotContainsString('001234', (string) $raw);
        $this->assertSame('SC-2024-001234', Customer::sole()->senior_citizen_id);

        $this->actingAs($this->owner)
            ->get(route('catalog.customers.index', ['search' => 'sc 2024 001234']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.senior_citizen_id_masked', '•••• 1234')
                ->missing('rows.data.0.senior_citizen_id'));
    }

    public function test_blank_id_on_edit_keeps_the_stored_number()
    {
        $customer = Customer::factory()->senior()->create();
        $original = $customer->senior_citizen_id;

        $this->actingAs($this->owner)->put(route('catalog.customers.update', $customer), [
            'name' => 'Renamed',
            'senior_citizen_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame($original, $customer->fresh()?->senior_citizen_id);
    }

    public function test_id_numbers_never_reach_the_audit_log()
    {
        Customer::factory()->create(['senior_citizen_id' => 'SC-999-8888']);

        $this->assertFalse(DB::table('activity_log')->where('attribute_changes', 'like', '%8888%')->exists());
    }

    public function test_cashiers_can_look_up_customers_at_the_pos()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);
        Customer::factory()->create(['name' => 'Juan Dela Cruz']);

        $this->actingAs($cashier)
            ->getJson(route('catalog.lookup.customers', ['q' => 'Juan']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Juan Dela Cruz');
    }

    public function test_suppliers_are_created_and_expired_licences_flagged()
    {
        $this->actingAs($this->owner)->post(route('catalog.suppliers.store'), [
            'code' => 'ZUEL',
            'name' => 'Zuellig Pharma',
            'payment_terms_days' => 30,
            'fda_license_expires_on' => now()->subDay()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Supplier::sole()->fdaLicenseExpired());

        $this->actingAs($this->owner)
            ->get(route('catalog.suppliers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('rows.data.0.fda_license_expired', true));
    }

    public function test_lookup_types_are_whitelisted()
    {
        $this->actingAs($this->owner)->get(route('catalog.lookups.index', 'generic-names'))->assertOk();
        $this->actingAs($this->owner)->get(route('catalog.lookups.index', 'users'))->assertNotFound();
    }

    public function test_prescribers_are_created_with_unique_prc_numbers()
    {
        $this->actingAs($this->owner)->post(route('catalog.prescribers.store'), ['name' => 'Dr. Santos', 'prc_license_number' => '0123456'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('catalog.prescribers.store'), ['name' => 'Dr. Reyes', 'prc_license_number' => '0123456'])->assertSessionHasErrors('prc_license_number');
    }
}
