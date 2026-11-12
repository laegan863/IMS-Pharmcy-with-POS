<?php

namespace App\Services\Sales;

use App\Models\Payment;
use App\Models\Reading;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The end-of-day Z-reading for one terminal: every sale, void and refund
 * since the last Z-reading, the invoice number range, the VAT breakdown, and
 * the terminal's grand total before and after. The grand total is never
 * reset; the Z counter goes up by one each time.
 */
class ZReadingService
{
    public function __construct(private DocumentNumbers $numbers) {}

    public function generate(Terminal $terminal, User $user): Reading
    {
        return DB::transaction(function () use ($terminal, $user): Reading {
            $terminal = Terminal::withoutGlobalScopes()->lockForUpdate()->findOrFail($terminal->id);

            if (Shift::withoutGlobalScopes()->where('terminal_id', $terminal->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['terminal' => __('Close every shift on this terminal before the Z-reading.')]);
            }

            $last = Reading::withoutGlobalScopes()->where('terminal_id', $terminal->id)->latest('id')->first();
            $start = $last->period_end ?? Sale::withoutGlobalScopes()->where('terminal_id', $terminal->id)->min('completed_at') ?? now();
            $end = now();

            $sales = Sale::withoutGlobalScopes()
                ->where('terminal_id', $terminal->id)
                ->where('completed_at', '>', $last->period_end ?? '1970-01-01')
                ->where('completed_at', '<=', $end);

            $adjustments = SaleAdjustment::withoutGlobalScopes()
                ->where('terminal_id', $terminal->id)
                ->where('created_at', '>', $last->period_end ?? '1970-01-01')
                ->where('created_at', '<=', $end);

            $saleIds = (clone $sales)->pluck('id');
            $netSales = Decimal::round((string) (clone $sales)->sum('total'));
            $voids = Decimal::round((string) (clone $adjustments)->where('type', 'void')->sum('amount'));
            $refunds = Decimal::round((string) (clone $adjustments)->where('type', 'refund')->sum('amount'));

            $data = [
                'invoice_count' => (clone $sales)->count(),
                'first_invoice' => (clone $sales)->orderBy('id')->value('number'),
                'last_invoice' => (clone $sales)->orderByDesc('id')->value('number'),
                'gross_sales' => Decimal::round((string) (clone $sales)->sum('gross_amount')),
                'senior_discounts' => Decimal::round((string) (clone $sales)->where('discount_type', 'senior')->sum('statutory_discount')),
                'pwd_discounts' => Decimal::round((string) (clone $sales)->where('discount_type', 'pwd')->sum('statutory_discount')),
                'net_sales' => $netSales,
                'vatable_sales' => Decimal::round((string) (clone $sales)->sum('vatable_sales')),
                'vat_amount' => Decimal::round((string) (clone $sales)->sum('vat_amount')),
                'vat_exempt_sales' => Decimal::round((string) (clone $sales)->sum('vat_exempt_sales')),
                'zero_rated_sales' => Decimal::round((string) (clone $sales)->sum('zero_rated_sales')),
                'void_count' => (clone $adjustments)->where('type', 'void')->count(),
                'voids' => $voids,
                'refund_count' => (clone $adjustments)->where('type', 'refund')->count(),
                'refunds' => $refunds,
                'first_void' => (clone $adjustments)->where('type', 'void')->orderBy('id')->value('number'),
                'last_void' => (clone $adjustments)->where('type', 'void')->orderByDesc('id')->value('number'),
                'first_refund' => (clone $adjustments)->where('type', 'refund')->orderBy('id')->value('number'),
                'last_refund' => (clone $adjustments)->where('type', 'refund')->orderByDesc('id')->value('number'),
                'net_after_adjustments' => Decimal::sub(Decimal::sub($netSales, $voids), $refunds),
                'payments' => Payment::query()->whereIn('sale_id', $saleIds)->groupBy('method')->pluck(DB::raw('SUM(amount)'), 'method')
                    ->map(fn ($amount) => Decimal::round((string) $amount))->all(),
            ];

            // Grand total before = after minus the sales in this period.
            $after = $terminal->grand_total;
            $before = Decimal::sub($after, $netSales);

            $terminal->z_counter++;
            $terminal->save();

            return Reading::query()->create([
                'branch_id' => $terminal->branch_id,
                'terminal_id' => $terminal->id,
                'type' => 'z',
                'number' => $this->numbers->next(DocumentNumbers::Z_READING, $terminal->branch_id, $terminal->id),
                'z_counter' => $terminal->z_counter,
                'period_start' => $start,
                'period_end' => $end,
                'grand_total_before' => $before,
                'grand_total_after' => $after,
                'data' => $data,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);
        });
    }
}
