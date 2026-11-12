<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * The filters every report receives: branch and an inclusive date range in
 * the store's timezone.
 */
final readonly class ReportFilters
{
    public function __construct(
        public int $branchId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public function start(): CarbonImmutable
    {
        return $this->from->startOfDay();
    }

    public function end(): CarbonImmutable
    {
        return $this->to->endOfDay();
    }
}
