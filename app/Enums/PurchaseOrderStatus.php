<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case PartlyReceived = 'partly_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::PartlyReceived => 'Partly received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether goods can still be received against the order.
     */
    public function isReceivable(): bool
    {
        return $this === self::Approved || $this === self::PartlyReceived;
    }
}
