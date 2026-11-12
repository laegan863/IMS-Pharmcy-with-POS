<?php

namespace App\Services\Stock;

use RuntimeException;

/**
 * Thrown when a branch does not hold enough sellable stock (active,
 * unexpired batches) to issue the quantity asked for.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct("Not enough stock of {$productName}: {$requested} requested, {$available} available.");
    }
}
