<?php

namespace App\Enums;

enum ProductClassification: string
{
    case Rx = 'rx';
    case Otc = 'otc';
    case DangerousDrug = 'dangerous_drug';
    case NonDrug = 'non_drug';

    public function label(): string
    {
        return match ($this) {
            self::Rx => 'Prescription (Rx)',
            self::Otc => 'Over the counter',
            self::DangerousDrug => 'Dangerous drug (S2)',
            self::NonDrug => 'Non-drug',
        };
    }

    /**
     * Whether a prescription must be recorded before it is sold.
     */
    public function needsPrescription(): bool
    {
        return $this === self::Rx || $this === self::DangerousDrug;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
