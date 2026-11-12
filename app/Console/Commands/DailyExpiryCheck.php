<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\StockBalance;
use App\Models\User;
use App\Notifications\ExpiryAlert;
use App\Services\Stock\StockControlService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Runs each morning: marks batches past their expiry date as expired and
 * tells each branch's staff which of their batches reached an alert
 * threshold today (stock.expiry_alert_days, 90/60/30 by default).
 */
#[Signature('stock:daily-expiry-check')]
#[Description('Mark expired batches and send 90/60/30-day expiry alerts')]
class DailyExpiryCheck extends Command
{
    public function handle(StockControlService $control, Settings $settings): int
    {
        $expired = $control->expireBatches();
        $this->info("{$expired} batch(es) marked expired.");

        $thresholds = array_map('intval', (array) $settings->get('stock.expiry_alert_days'));
        $today = CarbonImmutable::today();
        $dates = array_map(fn (int $days) => $today->addDays($days)->toDateString(), $thresholds);
        $dates[] = $today->subDay()->toDateString(); // expired as of today

        foreach (Branch::query()->where('is_active', true)->get() as $branch) {
            $batches = StockBalance::withoutGlobalScope(BranchScope::class)
                ->with(['batch', 'product.genericName', 'product.dosageForm'])
                ->where('branch_id', $branch->id)
                ->where('quantity', '>', 0)
                ->whereHas('batch', function ($query) use ($dates): void {
                    $query->where(function ($query) use ($dates): void {
                        foreach ($dates as $date) {
                            $query->orWhereDate('expires_on', $date);
                        }
                    });
                })
                ->get()
                ->map(fn (StockBalance $balance) => [
                    'product' => $balance->product->displayName(),
                    'lot' => $balance->batch->lot_number,
                    'expires_on' => $balance->batch->expires_on->toDateString(),
                    'days' => $balance->batch->daysToExpiry(),
                    'quantity' => $balance->quantity,
                ])
                ->values()
                ->all();

            if ($batches === []) {
                continue;
            }

            $recipients = User::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->permission(Permission::StockView->value)
                ->get()
                ->filter(fn (User $user) => $user->can(Permission::DashboardView->value));

            Notification::send($recipients, new ExpiryAlert($branch->name, array_values($batches)));
            $this->info("{$branch->name}: ".count($batches).' batch(es) alerted.');
        }

        return self::SUCCESS;
    }
}
