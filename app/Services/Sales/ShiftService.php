<?php

namespace App\Services\Sales;

use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Support\AuditContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cashier shifts: open with a cash float, record cash in and out, close with
 * a count by denomination. Closing stores the X-reading for the shift.
 */
class ShiftService
{
    /** Peso notes and coins accepted in a cash count. */
    public const DENOMINATIONS = ['1000', '500', '200', '100', '50', '20', '10', '5', '1', '0.25', '0.10', '0.05', '0.01'];

    public function __construct(private DocumentNumbers $numbers) {}

    public function open(User $user, Terminal $terminal, string $openingCash): Shift
    {
        if (! $terminal->is_active || $terminal->branch_id !== $user->branch_id) {
            throw ValidationException::withMessages(['terminal_id' => __('Choose an active terminal in your branch.')]);
        }

        return DB::transaction(function () use ($user, $terminal, $openingCash): Shift {
            Terminal::withoutGlobalScopes()->lockForUpdate()->findOrFail($terminal->id);

            if (Shift::withoutGlobalScopes()->where('terminal_id', $terminal->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['terminal_id' => __('This terminal already has an open shift.')]);
            }

            if (Shift::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['terminal_id' => __('You already have an open shift on another terminal.')]);
            }

            $shift = Shift::query()->create([
                'branch_id' => $terminal->branch_id,
                'terminal_id' => $terminal->id,
                'user_id' => $user->id,
                'number' => $this->numbers->next(DocumentNumbers::SHIFT, $terminal->branch_id, $terminal->id),
                'status' => 'open',
                'opening_cash' => Decimal::round($openingCash),
                'opened_at' => now(),
            ]);

            if (request()->hasSession()) {
                request()->session()->put(AuditContext::TERMINAL_SESSION_KEY, $terminal->id);
            }

            return $shift;
        });
    }

    /**
     * The user's open shift, if any.
     */
    public function current(User $user): ?Shift
    {
        return Shift::withoutGlobalScopes()->with('terminal')->where('user_id', $user->id)->where('status', 'open')->first();
    }

    public function cashMovement(Shift $shift, User $user, string $type, string $amount, string $reason): CashMovement
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages(['amount' => __('The shift is closed.')]);
        }

        return CashMovement::query()->create([
            'branch_id' => $shift->branch_id,
            'shift_id' => $shift->id,
            'type' => $type === 'out' ? 'out' : 'in',
            'amount' => Decimal::round($amount),
            'reason' => $reason,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Closes the shift with the counted cash.
     *
     * @param  array<string, int>  $denominations  pieces counted, keyed by face value
     */
    public function close(Shift $shift, array $denominations): Shift
    {
        return DB::transaction(function () use ($shift, $denominations): Shift {
            $shift = Shift::withoutGlobalScopes()->lockForUpdate()->findOrFail($shift->id);

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages(['shift' => __('This shift is already closed.')]);
            }

            $counted = '0.00';
            $clean = [];

            foreach (self::DENOMINATIONS as $face) {
                $pieces = max(0, (int) ($denominations[$face] ?? 0));
                $clean[$face] = $pieces;
                $counted = Decimal::add($counted, Decimal::mul($face, $pieces));
            }

            $reading = $this->xReading($shift);

            $shift->fill([
                'status' => 'closed',
                'closed_at' => now(),
                'denominations' => $clean,
                'counted_cash' => $counted,
                'expected_cash' => $reading['expected_cash'],
                'variance' => Decimal::sub($counted, $reading['expected_cash']),
                'x_reading' => $reading,
            ])->save();

            if (request()->hasSession()) {
                request()->session()->forget(AuditContext::TERMINAL_SESSION_KEY);
            }

            return $shift;
        });
    }

    /**
     * Totals for the shift so far. Printed at shift close; reading it does not
     * reset anything.
     *
     * @return array<string, mixed>
     */
    public function xReading(Shift $shift): array
    {
        $sales = Sale::withoutGlobalScopes()->where('shift_id', $shift->id);
        $saleIds = (clone $sales)->pluck('id');

        $paymentsByMethod = Payment::query()
            ->whereIn('sale_id', $saleIds)
            ->groupBy('method')
            ->pluck(DB::raw('SUM(amount)'), 'method')
            ->map(fn ($amount) => Decimal::round((string) $amount));

        $change = Decimal::round((string) (clone $sales)->sum('change_due'));
        $cashPayments = (string) ($paymentsByMethod['cash'] ?? '0.00');
        $adjustments = SaleAdjustment::withoutGlobalScopes()->where('shift_id', $shift->id);
        $refundsCash = Decimal::round((string) (clone $adjustments)->where('type', 'refund')->sum('amount'));
        $voidsCash = $this->voidedCash($shift);
        $cashIn = Decimal::round((string) CashMovement::withoutGlobalScopes()->where('shift_id', $shift->id)->where('type', 'in')->sum('amount'));
        $cashOut = Decimal::round((string) CashMovement::withoutGlobalScopes()->where('shift_id', $shift->id)->where('type', 'out')->sum('amount'));

        $expected = Decimal::sum([
            $shift->opening_cash,
            Decimal::sub($cashPayments, $change),
            $cashIn,
            '-'.$cashOut,
            '-'.$refundsCash,
            '-'.$voidsCash,
        ]);

        return [
            'shift_number' => $shift->number,
            'opened_at' => $shift->opened_at->toIso8601String(),
            'printed_at' => now()->toIso8601String(),
            'invoice_count' => (clone $sales)->count(),
            'first_invoice' => (clone $sales)->orderBy('id')->value('number'),
            'last_invoice' => (clone $sales)->orderByDesc('id')->value('number'),
            'gross_sales' => Decimal::round((string) (clone $sales)->sum('gross_amount')),
            'statutory_discounts' => Decimal::round((string) (clone $sales)->sum('statutory_discount')),
            'net_sales' => Decimal::round((string) (clone $sales)->sum('total')),
            'vatable_sales' => Decimal::round((string) (clone $sales)->sum('vatable_sales')),
            'vat_amount' => Decimal::round((string) (clone $sales)->sum('vat_amount')),
            'vat_exempt_sales' => Decimal::round((string) (clone $sales)->sum('vat_exempt_sales')),
            'zero_rated_sales' => Decimal::round((string) (clone $sales)->sum('zero_rated_sales')),
            'voids' => Decimal::round((string) (clone $adjustments)->where('type', 'void')->sum('amount')),
            'void_count' => (clone $adjustments)->where('type', 'void')->count(),
            'refunds' => $refundsCash,
            'refund_count' => (clone $adjustments)->where('type', 'refund')->count(),
            'payments' => $paymentsByMethod->all(),
            'opening_cash' => $shift->opening_cash,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expected,
        ];
    }

    /**
     * Cash handed back for voided sales of this shift: the cash tendered on
     * the voided sale less the change given.
     */
    private function voidedCash(Shift $shift): string
    {
        $voided = SaleAdjustment::withoutGlobalScopes()
            ->where('shift_id', $shift->id)
            ->where('type', 'void')
            ->pluck('sale_id');

        if ($voided->isEmpty()) {
            return '0.00';
        }

        $cash = (string) Payment::query()->whereIn('sale_id', $voided)->where('method', 'cash')->sum('amount');
        $change = (string) Sale::withoutGlobalScopes()->whereIn('id', $voided)->sum('change_due');

        return Decimal::sub($cash, $change);
    }
}
