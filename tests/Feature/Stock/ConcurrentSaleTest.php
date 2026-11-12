<?php

namespace Tests\Feature\Stock;

use App\Models\Branch;
use App\Models\Product;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Two terminals sell the last unit at the same moment: exactly one sale goes
 * through. Runs two real PHP processes against MySQL/MariaDB, because only a
 * real database shows row-lock behaviour. Skipped on SQLite.
 */
class ConcurrentSaleTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_simultaneous_sales_of_the_last_unit_only_one_succeeds()
    {
        $connection = DB::connection();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Row locking needs MySQL or MariaDB.');
        }

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        app(StockService::class)->receive($branch->id, $product, 'LAST', now()->addYear()->toDateString(), 1, '4.00');

        // Each process sells one unit inside a transaction and holds it open
        // for a moment, so the second process arrives while the first holds
        // the lock on the batch balance.
        $code = sprintf(
            'try { Illuminate\Support\Facades\DB::transaction(function () { '
            .'app(App\Services\Stock\StockService::class)->issue(%d, App\Models\Product::findOrFail(%d), 1, App\Enums\StockMovementType::Sale); '
            .'usleep(1500000); }); echo "SOLD"; } '
            .'catch (App\Services\Stock\InsufficientStockException $e) { echo "NONE"; }',
            $branch->id,
            $product->id,
        );

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $connection->getName(),
            'DB_HOST' => (string) $connection->getConfig('host'),
            'DB_PORT' => (string) $connection->getConfig('port'),
            'DB_DATABASE' => (string) $connection->getConfig('database'),
            'DB_USERNAME' => (string) $connection->getConfig('username'),
            'DB_PASSWORD' => (string) $connection->getConfig('password'),
        ];

        $command = [PHP_BINARY, base_path('artisan'), 'tinker', '--execute='.$code];

        $results = Process::pool(function (Pool $pool) use ($command, $env) {
            $pool->as('first')->path(base_path())->env($env)->timeout(60)->command($command);
            $pool->as('second')->path(base_path())->env($env)->timeout(60)->command($command);
        })->start()->wait();

        $outputs = [trim($results['first']->output()), trim($results['second']->output())];
        sort($outputs);

        $this->assertSame(['NONE', 'SOLD'], $outputs, 'Errors: '.$results['first']->errorOutput().$results['second']->errorOutput());
        $this->assertSame(0, app(StockService::class)->onHand($branch->id, $product->id));
    }
}
