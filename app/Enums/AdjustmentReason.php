<?php

namespace App\Enums;

/**
 * Why stock was adjusted. Disposal reasons are used on disposals.
 */
enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Lost = 'lost';
    case Found = 'found';
    case DataEntryError = 'data_entry_error';
    case Sample = 'sample';
    case Recalled = 'recalled';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Damaged',
            self::Expired => 'Expired',
            self::Lost => 'Lost or stolen',
            self::Found => 'Found',
            self::DataEntryError => 'Data entry error',
            self::Sample => 'Sample / internal use',
            self::Recalled => 'Recalled',
            self::Other => 'Other',
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
