<?php

namespace App\Exports;

use App\Reports\Report;
use App\Reports\ReportFilters;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Any report as an Excel sheet, streamed row by row.
 */
class ReportExport implements FromGenerator, WithHeadings, WithTitle
{
    public function __construct(private Report $report, private ReportFilters $filters) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_values(array_map(fn (array $column) => $column[0], $this->report->columns()));
    }

    public function generator(): \Generator
    {
        $keys = array_keys($this->report->columns());

        foreach ($this->report->rows($this->filters) as $row) {
            yield array_map(fn (string $key) => $row[$key] ?? null, $keys);
        }
    }

    public function title(): string
    {
        return mb_substr($this->report->title(), 0, 31);
    }
}
