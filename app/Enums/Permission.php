<?php

namespace App\Enums;

/**
 * Every permission in the system, named module.action.
 *
 * This enum is the only list of permissions. RolesAndPermissionsSeeder syncs it
 * to the database, and Role::permissions() decides who gets what.
 */
enum Permission: string
{
    // Administration
    case DashboardView = 'dashboard.view';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case SettingsManage = 'settings.manage';
    case BranchesManage = 'branches.manage';
    case AuditView = 'audit.view';
    case BackupsManage = 'backups.manage';

    // Master data
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case ProductsImport = 'products.import';
    case PricesManage = 'prices.manage';
    case SuppliersManage = 'suppliers.manage';
    case CustomersManage = 'customers.manage';
    case PrescribersManage = 'prescribers.manage';

    // Stock
    case StockView = 'stock.view';
    case StockImport = 'stock.import';
    case StockAdjust = 'stock.adjust';
    case StockAdjustApprove = 'stock.adjust-approve';
    case StockCount = 'stock.count';
    case StockCountApprove = 'stock.count-approve';
    case StockTransfer = 'stock.transfer';
    case StockDispose = 'stock.dispose';
    case StockQuarantine = 'stock.quarantine';

    // Purchasing
    case PurchaseOrdersManage = 'purchase-orders.manage';
    case PurchaseOrdersApprove = 'purchase-orders.approve';
    case ReceiptsManage = 'receipts.manage';
    case SupplierReturnsManage = 'supplier-returns.manage';

    // POS
    case PosSell = 'pos.sell';
    case PosVoid = 'pos.void';
    case PosRefund = 'pos.refund';
    case PosPriceOverride = 'pos.price-override';
    case PosBatchOverride = 'pos.batch-override';
    case PosReadings = 'pos.readings';

    // Compliance
    case PrescriptionsManage = 'prescriptions.manage';
    case DangerousDrugsDispense = 'dangerous-drugs.dispense';
    case ComplianceReports = 'compliance.reports';

    // Reports
    case ReportsSales = 'reports.sales';
    case ReportsInventory = 'reports.inventory';
    case ReportsProfit = 'reports.profit';

    /**
     * The module this permission belongs to, for grouping on the Roles page.
     */
    public function module(): string
    {
        return match ($this) {
            self::DashboardView, self::UsersManage, self::RolesManage, self::SettingsManage,
            self::BranchesManage, self::AuditView, self::BackupsManage => 'Administration',
            self::ProductsView, self::ProductsManage, self::ProductsImport, self::PricesManage,
            self::SuppliersManage, self::CustomersManage, self::PrescribersManage => 'Catalog',
            self::StockView, self::StockImport, self::StockAdjust, self::StockAdjustApprove, self::StockCount,
            self::StockCountApprove, self::StockTransfer, self::StockDispose, self::StockQuarantine => 'Inventory',
            self::PurchaseOrdersManage, self::PurchaseOrdersApprove, self::ReceiptsManage,
            self::SupplierReturnsManage => 'Purchasing',
            self::PosSell, self::PosVoid, self::PosRefund, self::PosPriceOverride, self::PosBatchOverride,
            self::PosReadings => 'Point of sale',
            self::PrescriptionsManage, self::DangerousDrugsDispense, self::ComplianceReports => 'Compliance',
            self::ReportsSales, self::ReportsInventory, self::ReportsProfit => 'Reports',
        };
    }

    /**
     * What the permission allows, in plain words.
     */
    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'See the full branch dashboard',
            self::UsersManage => 'Create and edit staff accounts',
            self::RolesManage => 'Create and edit roles',
            self::SettingsManage => 'Change store settings',
            self::BranchesManage => 'Manage branches and terminals',
            self::AuditView => 'View the audit log',
            self::BackupsManage => 'Manage backups',
            self::ProductsView => 'View products and prices',
            self::ProductsManage => 'Create and edit products and lookups',
            self::ProductsImport => 'Import the product list',
            self::PricesManage => 'Change selling prices',
            self::SuppliersManage => 'Manage suppliers',
            self::CustomersManage => 'Manage customers',
            self::PrescribersManage => 'Manage prescribers',
            self::StockView => 'View stock on hand and batches',
            self::StockImport => 'Import opening stock',
            self::StockAdjust => 'Draft stock adjustments',
            self::StockAdjustApprove => 'Approve adjustments and disposals',
            self::StockCount => 'Count stock',
            self::StockCountApprove => 'Post stock counts',
            self::StockTransfer => 'Transfer stock between branches',
            self::StockDispose => 'Draft disposals of expired stock',
            self::StockQuarantine => 'Quarantine and release batches',
            self::PurchaseOrdersManage => 'Draft purchase orders',
            self::PurchaseOrdersApprove => 'Approve purchase orders',
            self::ReceiptsManage => 'Receive goods',
            self::SupplierReturnsManage => 'Return goods to suppliers',
            self::PosSell => 'Sell at the POS',
            self::PosVoid => 'Approve voids',
            self::PosRefund => 'Approve refunds',
            self::PosPriceOverride => 'Approve price overrides',
            self::PosBatchOverride => 'Approve selling from a chosen batch',
            self::PosReadings => 'Run Z-readings',
            self::PrescriptionsManage => 'Record and view prescriptions',
            self::DangerousDrugsDispense => 'Dispense dangerous drugs (S2)',
            self::ComplianceReports => 'View BIR and compliance books',
            self::ReportsSales => 'View sales reports',
            self::ReportsInventory => 'View inventory reports',
            self::ReportsProfit => 'View cost and profit',
        };
    }

    /**
     * Permissions grouped by module, in display order.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function byModule(): array
    {
        $modules = ['Administration' => [], 'Catalog' => [], 'Inventory' => [], 'Purchasing' => [], 'Point of sale' => [], 'Compliance' => [], 'Reports' => []];

        foreach (self::cases() as $permission) {
            $modules[$permission->module()][] = ['value' => $permission->value, 'label' => $permission->label()];
        }

        return $modules;
    }
}
