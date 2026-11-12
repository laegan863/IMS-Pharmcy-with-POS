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
}
