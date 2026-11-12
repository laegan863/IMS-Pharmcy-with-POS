<?php

namespace App\Exports;

use App\Services\Catalog\ProductImporter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The product import template with one example row.
 */
class ProductImportTemplate implements FromArray, WithHeadings
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ProductImporter::COLUMNS;
    }

    /**
     * @return list<list<string|int>>
     */
    public function array(): array
    {
        return [[
            'BIO-500', 'Paracetamol', 'Biogesic', '500 mg', 'Tablet', 'Unilab', 'Analgesics',
            'otc', 'vatable', 'DR-XY12345', 'room', 200,
            'Tablet', '5.50', '4800010000011',
            'Blister', 10, '52.00', '4800010000028',
            'Box', 100, '500.00', '4800010000035',
        ]];
    }
}
