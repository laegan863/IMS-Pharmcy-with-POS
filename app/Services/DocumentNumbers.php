<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Terminal;
use App\Settings\Settings;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Hands out sequential document numbers with no gaps.
 *
 * Call next() inside the transaction that posts the document: the counter
 * row is locked until the transaction ends, and if posting fails the
 * increment is rolled back with it.
 *
 * Format: PREFIX-BRANCH-00000001 for branch series, and
 * PREFIX-BRANCH-TERMINAL-00000001 for per-terminal POS series. Prefixes and
 * the padding come from Settings (numbering.*).
 */
class DocumentNumbers
{
    public const PURCHASE_ORDER = 'purchase_order';

    public const GOODS_RECEIPT = 'goods_receipt';

    public const SUPPLIER_RETURN = 'supplier_return';

    public const SALES_INVOICE = 'invoice';

    public const VOID = 'void';

    public const REFUND = 'refund';

    public const ADJUSTMENT = 'adjustment';

    public const STOCK_COUNT = 'stock_count';

    public const TRANSFER = 'transfer';

    public const DISPOSAL = 'disposal';

    public const SHIFT = 'shift';

    public const Z_READING = 'z_reading';

    public function __construct(private Settings $settings) {}

    public function next(string $series, int $branchId, ?int $terminalId = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be taken inside the posting transaction.');
        }

        $terminalKey = $terminalId ?? 0;

        DB::table('document_sequences')->insertOrIgnore([
            'branch_id' => $branchId,
            'terminal_id' => $terminalKey,
            'series' => $series,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('document_sequences')
            ->where('branch_id', $branchId)
            ->where('terminal_id', $terminalKey)
            ->where('series', $series)
            ->lockForUpdate()
            ->first();

        $number = (int) ($sequence->last_number ?? 0) + 1;

        DB::table('document_sequences')
            ->where('branch_id', $branchId)
            ->where('terminal_id', $terminalKey)
            ->where('series', $series)
            ->update(['last_number' => $number, 'updated_at' => now()]);

        $parts = [$this->prefix($series), Branch::query()->whereKey($branchId)->value('code')];

        if ($terminalId !== null) {
            $parts[] = Terminal::withoutGlobalScopes()->whereKey($terminalId)->value('code');
        }

        $parts[] = str_pad((string) $number, $this->settings->int('numbering.pad_length'), '0', STR_PAD_LEFT);

        return implode('-', array_filter($parts, fn ($part) => $part !== null && $part !== ''));
    }

    private function prefix(string $series): string
    {
        return match ($series) {
            self::PURCHASE_ORDER => $this->settings->string('numbering.purchase_order_prefix'),
            self::GOODS_RECEIPT => $this->settings->string('numbering.goods_receipt_prefix'),
            self::SALES_INVOICE => $this->settings->string('numbering.invoice_prefix'),
            self::VOID => $this->settings->string('numbering.void_prefix'),
            self::REFUND => $this->settings->string('numbering.refund_prefix'),
            self::SUPPLIER_RETURN => 'SR',
            self::ADJUSTMENT => 'ADJ',
            self::STOCK_COUNT => 'CNT',
            self::TRANSFER => 'TR',
            self::DISPOSAL => 'DSP',
            self::SHIFT => 'SH',
            self::Z_READING => 'Z',
            default => strtoupper($series),
        };
    }
}
