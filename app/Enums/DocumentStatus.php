<?php

namespace App\Enums;

/**
 * Draft, then post: a draft can be edited; a posted document is read-only
 * and corrections are new documents.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
        };
    }
}
