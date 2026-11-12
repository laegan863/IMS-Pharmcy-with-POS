/** A product as returned by the catalog lookup endpoint. */
export type LookupUnit = {
    id: number;
    name: string;
    conversion_factor: number;
    is_base: boolean;
    is_sellable: boolean;
    is_purchasable: boolean;
    price: string | null;
};

export type LookupProduct = {
    id: number;
    sku: string;
    name: string;
    generic_name: string;
    classification: 'rx' | 'otc' | 'dangerous_drug' | 'non_drug';
    tax_class: 'vatable' | 'vat_exempt' | 'zero_rated';
    statutory_discount_eligible: boolean;
    /** Set when the search was an exact barcode: the unit that barcode is on. */
    scanned_unit_id: number | null;
    units: LookupUnit[];
};
