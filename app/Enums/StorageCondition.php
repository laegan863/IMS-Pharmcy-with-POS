<?php

namespace App\Enums;

enum StorageCondition: string
{
    case Room = 'room';
    case Cool = 'cool';
    case Refrigerated = 'refrigerated';
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Room temperature (below 30°C)',
            self::Cool => 'Cool (8–15°C)',
            self::Refrigerated => 'Refrigerated (2–8°C)',
            self::Frozen => 'Frozen',
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
