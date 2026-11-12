<?php

namespace App\Exports;

use App\Services\Stock\OpeningStockImporter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Opening stock template: one row per batch on the shelf. "unit" may be left
 * blank for the base unit; unit_cost is the cost of one of that unit.
 */
class OpeningStockTemplate implements FromArray, WithHeadings
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return OpeningStockImporter::COLUMNS;
    }

    /**
     * @return list<list<string|int>>
     */
    public function array(): array
    {
        return [
            ['BIO-500', 'L23A001', '2027-06-30', 'Box', 3, '450.00'],
            ['BIO-500', 'L23B044', '2027-11-30', 'Tablet', 35, '4.50'],
        ];
    }
}
