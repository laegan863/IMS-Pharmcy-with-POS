<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Settings\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function owner(): User
    {
        return User::factory()->withTwoFactor()->create()->assignRole(Role::Owner);
    }

    public function test_defaults_match_the_plan()
    {
        $settings = app(Settings::class);

        $this->assertSame('12.00', $settings->get('tax.vat_rate'));
        $this->assertSame('20.00', $settings->get('discounts.statutory_rate'));
        $this->assertSame([90, 60, 30], $settings->get('stock.expiry_alert_days'));
        $this->assertSame(6, $settings->get('stock.min_shelf_life_months'));
        $this->assertSame(15, $settings->get('pos.idle_timeout_minutes'));
        $this->assertTrue($settings->bool('pos.require_shift'));
    }

    public function test_unknown_settings_are_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        app(Settings::class)->get('tax.nope');
    }

    public function test_the_owner_can_view_settings()
    {
        $this->actingAs($this->owner())
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/store-settings')
                ->where('values', fn ($values) => $values['tax.vat_rate'] === '12.00'));
    }

    public function test_a_cashier_cannot_view_or_change_settings()
    {
        $cashier = User::factory()->create()->assignRole(Role::Cashier);

        $this->actingAs($cashier)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($cashier)->put(route('admin.settings.update'), ['settings' => []])->assertForbidden();
    }

    public function test_saving_casts_values_and_writes_only_changes()
    {
        $values = app(Settings::class)->all();
        $values['tax.vat_rate'] = '10';
        $values['stock.expiry_alert_days'] = '120, 90, 30';
        $values['pos.require_shift'] = false;

        $this->actingAs($this->owner())
            ->put(route('admin.settings.update'), ['settings' => Arr::undot($values)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.edit'));

        $settings = app(Settings::class);
        $this->assertSame('10.00', $settings->get('tax.vat_rate'));
        $this->assertSame([120, 90, 30], $settings->get('stock.expiry_alert_days'));
        $this->assertFalse($settings->get('pos.require_shift'));
        $this->assertSame(3, Setting::count());
        $this->assertDatabaseHas('activity_log', ['subject_type' => (new Setting)->getMorphClass(), 'event' => 'created']);
    }

    public function test_invalid_values_are_rejected()
    {
        $values = app(Settings::class)->all();
        $values['tax.vat_rate'] = '150';
        $values['numbering.invoice_prefix'] = 'SI 01';

        $this->actingAs($this->owner())
            ->put(route('admin.settings.update'), ['settings' => Arr::undot($values)])
            ->assertSessionHasErrors(['settings.tax.vat_rate', 'settings.numbering.invoice_prefix']);

        $this->assertSame(0, Setting::count());
    }
}
