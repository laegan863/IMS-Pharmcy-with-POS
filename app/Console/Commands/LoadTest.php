<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\DosageForm;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\Terminal;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Services\DashboardService;
use App\Services\Sales\SaleService;
use App\Services\Sales\ShiftService;
use App\Services\Stock\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Fills a test database with a full catalog and months of sales through the
 * real services, then times the screens that slow down as data grows.
 * Refuses to run in production.
 */
#[Signature('pharmacy:load-test {--products=2000} {--days=365} {--sales-per-day=150} {--lines=3}')]
#[Description('Generate a catalog and a year of sales in a test database, then time key queries')]
class LoadTest extends Command
{
    public function handle(StockService $stock, SaleService $sales, ShiftService $shifts, DashboardService $dashboard): int
    {
        if (app()->isProduction()) {
            $this->error('Never run the load test on the live database.');

            return self::FAILURE;
        }

        $productCount = (int) $this->option('products');
        $days = (int) $this->option('days');
        $perDay = (int) $this->option('sales-per-day');
        $linesPerSale = (int) $this->option('lines');

        $branch = Branch::query()->firstOrCreate(['code' => 'LOAD'], ['name' => 'Load test branch', 'is_active' => true]);
        $terminal = Terminal::withoutGlobalScopes()->firstOrCreate(['branch_id' => $branch->id, 'code' => 'LOAD-01'], ['name' => 'Load terminal', 'is_active' => true])->refresh();
        $cashier = User::factory()->create(['branch_id' => $branch->id, 'username' => 'load-'.Str::lower(Str::random(6))])->assignRole(Role::Cashier);

        $this->info("Creating {$productCount} products with stock...");
        // Random names so repeated runs never collide with earlier data.
        $products = Product::factory()->count($productCount)->state(fn () => [
            'sku' => 'LOAD-'.Str::upper(Str::random(10)),
            'brand_name' => 'Load '.Str::random(8),
            'generic_name_id' => GenericName::query()->create(['name' => 'Generic '.Str::random(10)])->id,
            'dosage_form_id' => DosageForm::query()->create(['name' => 'Form '.Str::random(10)])->id,
        ])->create()->load('units');
        $start = CarbonImmutable::today()->subDays($days);

        Date::setTestNow($start);

        foreach ($products as $product) {
            $stock->receive($branch->id, $product, 'LOAD-1', $start->addYears(3)->toDateString(), ($perDay * $days * $linesPerSale * 5 / max(1, $productCount)) + 500, '3.00');
        }

        $this->info("Posting {$perDay} sales a day for {$days} days...");
        $bar = $this->output->createProgressBar($days);

        for ($day = 0; $day < $days; $day++) {
            Date::setTestNow($start->addDays($day)->setTime(8, 0));
            $shift = $shifts->open($cashier, $terminal, '1000');

            for ($i = 0; $i < $perDay; $i++) {
                Date::setTestNow($start->addDays($day)->setTime(8, 0)->addMinutes((int) ($i * 600 / max(1, $perDay))));
                $lines = [];

                foreach ($products->random(min($linesPerSale, $productCount)) as $product) {
                    $lines[] = ['product_id' => $product->id, 'product_unit_id' => $product->units->firstWhere('is_base', true)?->id, 'quantity' => random_int(1, 5)];
                }

                $sales->complete([
                    'client_uuid' => (string) Str::uuid(),
                    'lines' => $lines,
                    'discount_type' => $i % 10 === 0 ? 'senior' : 'none',
                    'discount_id_number' => 'SC-LOAD',
                    'discount_holder_name' => 'Load test',
                    'payments' => [['method' => 'cash', 'amount' => '10000']],
                ], $cashier, $shift);
            }

            $shifts->close($shift, []);
            $bar->advance();
        }

        $bar->finish();
        Date::setTestNow();
        $this->newLine(2);

        $owner = User::factory()->create(['branch_id' => $branch->id])->assignRole(Role::Owner);
        $filters = new ReportFilters($branch->id, CarbonImmutable::today()->subDays(30), CarbonImmutable::today());
        $timings = [];

        $timings[] = ['Product search "para"', $this->time(fn () => Product::query()->search('para')->limit(20)->get())];
        $timings[] = ['Dashboard', $this->time(fn () => $dashboard->forUser($owner))];

        foreach (['sales-by-day', 'gross-profit', 'inventory-valuation', 'movers', 'expiry-list', 'sales-journal'] as $key) {
            $report = ReportRegistry::find($key);

            if ($report !== null) {
                $timings[] = ["Report: {$report->title()} (30 days)", $this->time(fn () => iterator_to_array($report->rows($filters), false))];
            }
        }

        $this->table(['Operation', 'Milliseconds'], $timings);
        $this->info('Aim: product search under 1,000 ms, dashboard and reports under 3,000 ms on the store server.');

        return self::SUCCESS;
    }

    private function time(callable $operation): int
    {
        $start = hrtime(true);
        $operation();

        return (int) ((hrtime(true) - $start) / 1_000_000);
    }
}
