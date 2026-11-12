<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Checks that every stock balance equals the sum of its movements. Runs
 * nightly; any difference means something wrote stock outside StockService.
 */
#[Signature('stock:verify-ledger')]
#[Description('Compare each stock balance with the sum of its movements')]
class VerifyStockLedger extends Command
{
    public function handle(): int
    {
        $sums = DB::table('stock_movements')
            ->select('branch_id', 'batch_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('branch_id', 'batch_id');

        // Balances whose quantity differs from their movements, and movement
        // groups with no balance row at all.
        $mismatches = DB::table('stock_balances as b')
            ->leftJoinSub($sums, 'm', fn ($join) => $join->on('m.branch_id', '=', 'b.branch_id')->on('m.batch_id', '=', 'b.batch_id'))
            ->whereRaw('b.quantity <> COALESCE(m.total, 0)')
            ->select('b.branch_id', 'b.batch_id', 'b.quantity as balance', DB::raw('COALESCE(m.total, 0) as movements'))
            ->get()
            ->concat(DB::query()
                ->fromSub($sums, 'm')
                ->leftJoin('stock_balances as b', fn ($join) => $join->on('m.branch_id', '=', 'b.branch_id')->on('m.batch_id', '=', 'b.batch_id'))
                ->whereNull('b.id')
                ->where('m.total', '<>', 0)
                ->select('m.branch_id', 'm.batch_id', DB::raw('0 as balance'), 'm.total as movements')
                ->get());

        if ($mismatches->isEmpty()) {
            $this->info('Stock ledger OK: every balance matches its movements.');

            return self::SUCCESS;
        }

        $this->error("Stock ledger mismatch on {$mismatches->count()} batch balance(s).");
        $this->table(['Branch', 'Batch', 'Balance', 'Sum of movements'], $mismatches->map(fn ($row) => (array) $row)->all());

        Log::critical('Stock ledger mismatch', ['rows' => $mismatches->all()]);
        activity('stock-check')
            ->event('ledger-mismatch')
            ->withProperties(['rows' => $mismatches->all()])
            ->log('Stock ledger mismatch found by nightly check');

        return self::FAILURE;
    }
}
