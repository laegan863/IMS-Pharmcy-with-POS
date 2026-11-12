<?php

namespace App\Services\Stock;

/**
 * Part of an issue taken from one batch.
 */
final readonly class Allocation
{
    public function __construct(
        public int $batchId,
        public int $quantity,
        public string $unitCost,
        public int $movementId,
    ) {}
}
