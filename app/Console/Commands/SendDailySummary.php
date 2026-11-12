<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\DailySummary;
use App\Reports\ReportFilters;
use App\Reports\SalesByDay;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the owner yesterday's figures for each branch: sales, discounts,
 * voids, refunds, cash variance and expiring stock.
 */
#[Signature('reports:daily-summary {--date= : The day to summarise (default yesterday)}')]
#[Description('Email the owner the daily summary for each branch')]
class SendDailySummary extends Command
{
    public function handle(): int
    {
        $day = $this->option('date') ? CarbonImmutable::parse((string) $this->option('date')) : CarbonImmutable::yesterday();
        $owners = User::role(Role::Owner->value)->where('is_active', true)->get();

        if ($owners->isEmpty()) {
            $this->warn('No active owner to send to.');

            return self::SUCCESS;
        }

        $branches = [];

        foreach (Branch::query()->where('is_active', true)->get() as $branch) {
            $row = collect((new SalesByDay)->rows(new ReportFilters($branch->id, $day, $day)))->first() ?? [];

            $branches[] = [
                'name' => $branch->name,
                'invoices' => (int) ($row['invoices'] ?? 0),
                'net' => (string) ($row['net'] ?? '0.00'),
                'discounts' => (string) ($row['discounts'] ?? '0.00'),
                'voids' => (string) ($row['voids'] ?? '0.00'),
                'refunds' => (string) ($row['refunds'] ?? '0.00'),
                'cash_variance' => Decimal::round((string) DB::table('shifts')->where('branch_id', $branch->id)
                    ->whereBetween('closed_at', [$day->startOfDay(), $day->endOfDay()])->sum('variance')),
                'expiring_30_days' => DB::table('stock_balances')
                    ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
                    ->where('stock_balances.branch_id', $branch->id)
                    ->where('stock_balances.quantity', '>', 0)
                    ->whereDate('batches.expires_on', '<=', $day->addDays(30)->toDateString())
                    ->count(),
            ];
        }

        Notification::send($owners, new DailySummary($day->toDateString(), $branches));
        $this->info('Daily summary sent to '.$owners->count().' owner(s).');

        return self::SUCCESS;
    }
}
