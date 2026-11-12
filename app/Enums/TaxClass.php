<?php

namespace App\Enums;

/**
 * How VAT applies to a product. The VAT-exempt medicine list grows by BIR
 * circular, so this is editable per product.
 */
enum TaxClass: string
{
    case Vatable = 'vatable';
    case VatExempt = 'vat_exempt';
    case ZeroRated = 'zero_rated';

    public function label(): string
    {
        return match ($this) {
            self::Vatable => 'VATable',
            self::VatExempt => 'VAT-exempt',
            self::ZeroRated => 'Zero-rated',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
