<?php

namespace App\Reports;

use App\Models\User;

/**
 * Every report, keyed by its URL segment.
 *
 * Compliance reports are the BIR back-end reports, the senior citizen and
 * PWD record books, the prescription book and the dangerous drug register.
 * The FDA allows these books to be kept as e-files; each is generated from
 * the sales data rather than kept by hand.
 */
final class ReportRegistry
{
    /**
     * @return array<string, Report>
     */
    public static function all(): array
    {
        return [
            'sales-journal' => new SalesJournal,
            'bir-sales-summary' => new BirSalesSummary,
            'senior-book' => new DiscountBook('senior'),
            'pwd-book' => new DiscountBook('pwd'),
            'voids-refunds' => new VoidsAndRefunds,
            'prescription-book' => new PrescriptionBook,
            'dangerous-drug-register' => new DangerousDrugRegister,
            'generic-price-list' => new GenericPriceList,
            'discounts-by-supplier' => new DiscountsBySupplier,
            'sales-by-day' => new SalesByDay,
            'sales-by-cashier' => new SalesByCashier,
            'sales-by-product' => new SalesByProduct,
            'gross-profit' => new SalesByProduct(withProfit: true),
            'inventory-valuation' => new InventoryValuation,
            'stock-movements' => new StockMovementsReport,
            'movers' => new MoversReport,
            'expiry-list' => new ExpiryList,
            'reorder-list' => new ReorderReport,
        ];
    }

    public static function find(string $key): ?Report
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Reports the user may open.
     *
     * @return array<string, Report>
     */
    public static function visibleTo(User $user): array
    {
        return array_filter(self::all(), fn (Report $report) => $user->can($report->permission()->value));
    }
}
