<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\Permission;
use App\Exports\ProductImportTemplate;
use App\Http\Controllers\Controller;
use App\Imports\HeadingRowSheet;
use App\Services\Catalog\ProductImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('catalog/products/import', [
            'columns' => ProductImporter::COLUMNS,
            'report' => null,
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ProductImportTemplate, 'product-import-template.xlsx');
    }

    public function store(Request $request, ProductImporter $importer): Response
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $sheets = Excel::toArray(new HeadingRowSheet, $request->file('file'));

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($sheets[0] ?? []);

        $report = $importer->import($rows, $request->user()?->can(Permission::PricesManage->value) ?? false);

        activity('import')
            ->event('products-imported')
            ->withProperties(['created' => $report['created'], 'updated' => $report['updated'], 'errors' => count($report['errors'])])
            ->log('Product import');

        return Inertia::render('catalog/products/import', [
            'columns' => ProductImporter::COLUMNS,
            'report' => $report,
        ]);
    }
}
