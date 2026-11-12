<?php

namespace App\Reports;

use App\Enums\Permission;
use Carbon\CarbonImmutable;

/**
 * A read-only report shown on screen and exported to Excel or PDF. Reports
 * only read: nothing here changes data.
 */
abstract class Report
{
    /** Shown in the report list and as the page and file title. */
    abstract public function title(): string;

    abstract public function description(): string;

    abstract public function permission(): Permission;

    /** Compliance, Sales, Inventory, ... for grouping in the list. */
    abstract public function group(): string;

    /**
     * Column key => [label, type] where type is text, number, money, date or datetime.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    abstract public function columns(): array;

    /**
     * @return iterable<array<string, mixed>>
     */
    abstract public function rows(ReportFilters $filters): iterable;

    /**
     * Whether the report uses the date range filter.
     */
    public function usesDates(): bool
    {
        return true;
    }

    /**
     * Columns summed in a totals row.
     *
     * @return list<string>
     */
    public function totalColumns(): array
    {
        return array_keys(array_filter($this->columns(), fn (array $column) => $column[1] === 'money'));
    }

    /**
     * Whether opening the report reveals personal data (logged when viewed).
     */
    public function containsPersonalData(): bool
    {
        return false;
    }

    /**
     * Default date range: the current month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function defaultRange(): array
    {
        return [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()];
    }
}
