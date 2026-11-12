<?php

namespace App\Enums;

/**
 * Why stock moved. The sign of the movement's quantity says which way.
 */
enum StockMovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case SupplierReturn = 'supplier_return';
    case Sale = 'sale';
    case SaleVoid = 'sale_void';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case CountVariance = 'count_variance';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Disposal = 'disposal';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Receipt => 'Goods receipt',
            self::SupplierReturn => 'Return to supplier',
            self::Sale => 'Sale',
            self::SaleVoid => 'Sale voided',
            self::Refund => 'Refund',
            self::Adjustment => 'Adjustment',
            self::CountVariance => 'Count variance',
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::Disposal => 'Disposal',
        };
    }
}
