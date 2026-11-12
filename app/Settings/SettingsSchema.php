<?php

namespace App\Settings;

/**
 * Every store setting: its group, label, type, default and validation rules.
 *
 * Types: string, text, bool, int, decimal, int_list, select.
 * Starting values follow the development plan; tax lists and discount rules
 * change, so they live here instead of in code.
 */
class SettingsSchema
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function groups(): array
    {
        return [
            'store' => ['label' => 'Store and BIR', 'description' => 'Details printed on invoices and BIR reports.'],
            'tax' => ['label' => 'Tax', 'description' => 'VAT rate and how prices are entered.'],
            'discounts' => ['label' => 'Statutory discounts', 'description' => 'Senior citizen and PWD discounts.'],
            'stock' => ['label' => 'Stock and expiry', 'description' => 'Batch selection, expiry alerts and receiving rules.'],
            'pos' => ['label' => 'POS controls', 'description' => 'Shifts, approvals and session timeout.'],
            'numbering' => ['label' => 'Numbering', 'description' => 'Document number prefixes. Numbers are sequential with no gaps, per terminal.'],
            'prescription' => ['label' => 'Prescription', 'description' => 'Rx and dangerous drug rules.'],
            'receipt' => ['label' => 'Receipt', 'description' => 'Extra lines printed on the receipt.'],
        ];
    }

    /**
     * @return array<string, array{group: string, label: string, type: string, default: mixed, rules: list<string>, help?: string, options?: array<string, string>}>
     */
    public static function definitions(): array
    {
        return [
            // Store and BIR
            'store.registered_name' => ['group' => 'store', 'label' => 'Registered name', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:150']],
            'store.trade_name' => ['group' => 'store', 'label' => 'Trade name', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:150']],
            'store.tin' => ['group' => 'store', 'label' => 'TIN', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:20', 'regex:/^[0-9-]*$/'], 'help' => 'Format 000-000-000-00000.'],
            'store.address' => ['group' => 'store', 'label' => 'Registered address', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:300']],
            'store.vat_registered' => ['group' => 'store', 'label' => 'VAT-registered', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'store.accreditation_number' => ['group' => 'store', 'label' => 'BIR accreditation no.', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:50'], 'help' => 'Certificate of Accreditation of the POS software.'],
            'store.fda_license_number' => ['group' => 'store', 'label' => 'FDA licence to operate no.', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:50']],

            // Tax
            'tax.vat_rate' => ['group' => 'tax', 'label' => 'VAT rate (%)', 'type' => 'decimal', 'default' => '12.00', 'rules' => ['required', 'numeric', 'min:0', 'max:100']],
            'tax.prices_include_vat' => ['group' => 'tax', 'label' => 'Prices are entered VAT-inclusive', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],

            // Statutory discounts
            'discounts.statutory_rate' => ['group' => 'discounts', 'label' => 'Senior/PWD discount (%)', 'type' => 'decimal', 'default' => '20.00', 'rules' => ['required', 'numeric', 'min:0', 'max:100']],
            'discounts.statutory_vat_exempt' => ['group' => 'discounts', 'label' => 'Senior/PWD purchases are VAT-exempt', 'type' => 'bool', 'default' => true, 'rules' => ['boolean'], 'help' => 'VAT is removed first, then the discount is taken.'],
            'discounts.allow_promo_stacking' => ['group' => 'discounts', 'label' => 'Allow promos on top of senior/PWD discount', 'type' => 'bool', 'default' => false, 'rules' => ['boolean']],
            'discounts.pwd_require_booklet' => ['group' => 'discounts', 'label' => 'Require PWD purchase booklet', 'type' => 'bool', 'default' => false, 'rules' => ['boolean'], 'help' => 'Seniors no longer need a booklet (DOH AO 2024-0017). Confirm the PWD rule.'],

            // Stock and expiry
            'stock.issue_rule' => ['group' => 'stock', 'label' => 'Batch selection rule', 'type' => 'select', 'default' => 'fefo', 'rules' => ['required', 'in:fefo'], 'options' => ['fefo' => 'First expiry, first out']],
            'stock.expiry_alert_days' => ['group' => 'stock', 'label' => 'Near-expiry alerts (days)', 'type' => 'int_list', 'default' => [90, 60, 30], 'rules' => ['required', 'array', 'min:1', 'max:5'], 'help' => 'Comma-separated, e.g. 90, 60, 30.'],
            'stock.min_shelf_life_months' => ['group' => 'stock', 'label' => 'Minimum shelf life at receiving (months)', 'type' => 'int', 'default' => 6, 'rules' => ['required', 'integer', 'min:0', 'max:60']],

            // POS controls
            'pos.require_shift' => ['group' => 'pos', 'label' => 'An open shift is required to sell', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'pos.supervisor_for_void' => ['group' => 'pos', 'label' => 'Supervisor PIN for voids', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'pos.supervisor_for_refund' => ['group' => 'pos', 'label' => 'Supervisor PIN for refunds', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'pos.supervisor_for_price_override' => ['group' => 'pos', 'label' => 'Supervisor PIN for price overrides', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'pos.idle_timeout_minutes' => ['group' => 'pos', 'label' => 'POS idle sign-out (minutes)', 'type' => 'int', 'default' => 15, 'rules' => ['required', 'integer', 'min:1', 'max:240']],

            // Numbering
            'numbering.invoice_prefix' => ['group' => 'numbering', 'label' => 'Sales invoice prefix', 'type' => 'string', 'default' => 'SI', 'rules' => ['required', 'string', 'max:6', 'alpha_dash']],
            'numbering.void_prefix' => ['group' => 'numbering', 'label' => 'Void prefix', 'type' => 'string', 'default' => 'VD', 'rules' => ['required', 'string', 'max:6', 'alpha_dash']],
            'numbering.refund_prefix' => ['group' => 'numbering', 'label' => 'Refund prefix', 'type' => 'string', 'default' => 'RF', 'rules' => ['required', 'string', 'max:6', 'alpha_dash']],
            'numbering.purchase_order_prefix' => ['group' => 'numbering', 'label' => 'Purchase order prefix', 'type' => 'string', 'default' => 'PO', 'rules' => ['required', 'string', 'max:6', 'alpha_dash']],
            'numbering.goods_receipt_prefix' => ['group' => 'numbering', 'label' => 'Goods receipt prefix', 'type' => 'string', 'default' => 'GR', 'rules' => ['required', 'string', 'max:6', 'alpha_dash']],
            'numbering.pad_length' => ['group' => 'numbering', 'label' => 'Number length (zero-padded)', 'type' => 'int', 'default' => 8, 'rules' => ['required', 'integer', 'min:4', 'max:12']],

            // Prescription
            'prescription.block_rx_without_prescription' => ['group' => 'prescription', 'label' => 'Block Rx items without a recorded prescription', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'prescription.dangerous_drugs_enabled' => ['group' => 'prescription', 'label' => 'Store sells dangerous drugs (S2)', 'type' => 'bool', 'default' => true, 'rules' => ['boolean'], 'help' => 'Turn off if out of scope; the dangerous drug register is then hidden.'],

            // Receipt
            'receipt.header_lines' => ['group' => 'receipt', 'label' => 'Extra header lines', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:300']],
            'receipt.footer_text' => ['group' => 'receipt', 'label' => 'Footer text', 'type' => 'text', 'default' => 'Thank you! This serves as your sales invoice.', 'rules' => ['nullable', 'string', 'max:300']],
        ];
    }

    /**
     * Validation rules keyed for a request whose "settings" field holds the values.
     *
     * @return array<string, list<string>>
     */
    public static function validationRules(): array
    {
        $rules = [];

        foreach (self::definitions() as $key => $definition) {
            $rules['settings.'.$key] = $definition['rules'];

            if ($definition['type'] === 'int_list') {
                $rules['settings.'.$key.'.*'] = ['integer', 'min:1', 'max:3650'];
            }
        }

        return $rules;
    }

    /**
     * Converts a submitted value to the setting's type.
     */
    public static function cast(string $key, mixed $value): mixed
    {
        $type = self::definitions()[$key]['type'];

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            'decimal' => number_format((float) $value, 2, '.', ''),
            'int_list' => array_values(array_unique(array_map('intval', (array) $value))),
            default => $value === null ? '' : (string) $value,
        };
    }
}
