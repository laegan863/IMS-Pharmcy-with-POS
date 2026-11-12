<?php

namespace App\Enums;

/**
 * Only active, unexpired batches can be sold or issued.
 */
enum BatchStatus: string
{
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Quarantined => 'Quarantined',
            self::Expired => 'Expired',
            self::Disposed => 'Disposed',
        };
    }
}
