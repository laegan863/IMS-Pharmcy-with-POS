<?php

namespace App\Enums;

/**
 * The default roles a new store starts with, and their starting permissions.
 * After installation roles are managed on the Roles page (App\Models\Role);
 * only Owner is locked. RolesAndPermissionsSeeder creates these once.
 */
enum Role: string
{
    case Owner = 'owner';
    case Pharmacist = 'pharmacist';
    case Cashier = 'cashier';
    case InventoryClerk = 'inventory-clerk';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Pharmacist => 'Pharmacist',
            self::Cashier => 'Cashier',
            self::InventoryClerk => 'Inventory clerk',
        };
    }

    /**
     * Whether this role must use two-factor authentication.
     */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [self::Owner, self::Pharmacist], true);
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),

            // The pharmacist supervises the counter and owns compliance.
            self::Pharmacist => [
                Permission::DashboardView,
                Permission::ProductsView,
                Permission::ProductsManage,
                Permission::PricesManage,
                Permission::CustomersManage,
                Permission::PrescribersManage,
                Permission::StockView,
                Permission::StockAdjust,
                Permission::StockAdjustApprove,
                Permission::StockCount,
                Permission::StockCountApprove,
                Permission::StockDispose,
                Permission::StockQuarantine,
                Permission::PurchaseOrdersApprove,
                Permission::PosSell,
                Permission::PosVoid,
                Permission::PosRefund,
                Permission::PosPriceOverride,
                Permission::PosBatchOverride,
                Permission::PosReadings,
                Permission::PrescriptionsManage,
                Permission::DangerousDrugsDispense,
                Permission::ComplianceReports,
                Permission::ReportsSales,
                Permission::ReportsInventory,
            ],

            self::Cashier => [
                Permission::ProductsView,
                Permission::CustomersManage,
                Permission::StockView,
                Permission::PosSell,
            ],

            self::InventoryClerk => [
                Permission::DashboardView,
                Permission::ProductsView,
                Permission::ProductsManage,
                Permission::ProductsImport,
                Permission::SuppliersManage,
                Permission::StockView,
                Permission::StockImport,
                Permission::StockAdjust,
                Permission::StockCount,
                Permission::StockTransfer,
                Permission::StockDispose,
                Permission::StockQuarantine,
                Permission::PurchaseOrdersManage,
                Permission::ReceiptsManage,
                Permission::SupplierReturnsManage,
                Permission::ReportsInventory,
            ],
        };
    }
}
