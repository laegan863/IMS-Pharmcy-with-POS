import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { Field } from '@/components/form/field';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, formatMoney } from '@/lib/format';
import { index, store, update } from '@/routes/catalog/products';

type Unit = {
    id: number | null;
    name: string;
    conversion_factor: number | string;
    is_base: boolean;
    is_sellable: boolean;
    is_purchasable: boolean;
    price: string | null;
};

type Barcode = { code: string; unit: string };

type Product = {
    id: number;
    sku: string;
    generic_name: string;
    brand_name: string | null;
    strength: string | null;
    dosage_form_id: number | null;
    manufacturer: string | null;
    category_id: number | null;
    classification: string;
    fda_registration_number: string | null;
    tax_class: string;
    tax_class_effective_from: string | null;
    statutory_discount_eligible: boolean;
    storage_condition: string;
    reorder_point: number;
    max_level: number | null;
    description: string | null;
    is_active: boolean;
    units: Unit[];
    barcodes: Barcode[];
};

type PriceChange = {
    id: number;
    unit: string | null;
    old_price: string | null;
    new_price: string | null;
    changed_by: string | null;
    changed_at: string;
};

type Props = {
    product: Product | null;
    priceHistory?: PriceChange[];
    genericNames: string[];
    manufacturers: string[];
    dosageForms: Option[];
    categories: Option[];
    classifications: Option[];
    taxClasses: Option[];
    storageConditions: Option[];
    canSetPrices: boolean;
};

const emptyUnit = (isBase = false): Unit => ({
    id: null,
    name: isBase ? 'Tablet' : '',
    conversion_factor: isBase ? 1 : '',
    is_base: isBase,
    is_sellable: true,
    is_purchasable: !isBase,
    price: '',
});

export default function ProductForm({
    product,
    priceHistory = [],
    genericNames,
    manufacturers,
    dosageForms,
    categories,
    classifications,
    taxClasses,
    storageConditions,
    canSetPrices,
}: Props) {
    const form = useForm({
        sku: product?.sku ?? '',
        generic_name: product?.generic_name ?? '',
        brand_name: product?.brand_name ?? '',
        strength: product?.strength ?? '',
        dosage_form_id: product?.dosage_form_id
            ? String(product.dosage_form_id)
            : '',
        manufacturer: product?.manufacturer ?? '',
        category_id: product?.category_id ? String(product.category_id) : '',
        classification: product?.classification ?? 'otc',
        fda_registration_number: product?.fda_registration_number ?? '',
        tax_class: product?.tax_class ?? 'vatable',
        tax_class_effective_from: product?.tax_class_effective_from ?? '',
        statutory_discount_eligible:
            product?.statutory_discount_eligible ?? true,
        storage_condition: product?.storage_condition ?? 'room',
        reorder_point: String(product?.reorder_point ?? 0),
        max_level: product?.max_level != null ? String(product.max_level) : '',
        description: product?.description ?? '',
        is_active: product?.is_active ?? true,
        units: product?.units ?? [emptyUnit(true)],
        barcodes: product?.barcodes ?? ([] as Barcode[]),
    });

    const errors = form.errors as Record<string, string | undefined>;
    const baseUnit = form.data.units.find((unit) => unit.is_base);

    const setUnit = (index: number, changes: Partial<Unit>) => {
        form.setData(
            'units',
            form.data.units.map((unit, i) => {
                if (i === index) {
                    return { ...unit, ...changes };
                }

                // Only one base unit.
                return changes.is_base ? { ...unit, is_base: false } : unit;
            }),
        );
    };

    const setBarcode = (index: number, changes: Partial<Barcode>) =>
        form.setData(
            'barcodes',
            form.data.barcodes.map((barcode, i) =>
                i === index ? { ...barcode, ...changes } : barcode,
            ),
        );

    const addBarcode = () =>
        form.setData('barcodes', [
            ...form.data.barcodes,
            { code: '', unit: baseUnit?.name ?? '' },
        ]);

    // A scanner types the code and presses Enter: add a new row instead of submitting.
    const onBarcodeKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addBarcode();
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (product) {
            form.put(update(product.id).url, { preserveScroll: true });
        } else {
            form.post(store().url);
        }
    };

    const title = product
        ? [product.brand_name, product.generic_name].filter(Boolean).join(' · ')
        : 'New product';

    return (
        <>
            <Head title={title} />
            <div className="px-4 py-6">
                <PageHeader title={title} />

                <form onSubmit={submit} className="max-w-4xl space-y-10">
                    <section className="space-y-6">
                        <Heading variant="small" title="Identity" />
                        <div className="grid gap-6 sm:grid-cols-3">
                            <TextField
                                id="sku"
                                label="SKU"
                                value={form.data.sku}
                                onChange={(value) => form.setData('sku', value)}
                                error={errors.sku}
                                required
                            />
                            <Field
                                label="Generic name"
                                htmlFor="generic_name"
                                error={errors.generic_name}
                                className="sm:col-span-2"
                                help="Pick from the list or type a new one."
                                required
                            >
                                <Input
                                    id="generic_name"
                                    list="generic-names"
                                    value={form.data.generic_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'generic_name',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <datalist id="generic-names">
                                    {genericNames.map((name) => (
                                        <option key={name} value={name} />
                                    ))}
                                </datalist>
                            </Field>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-3">
                            <TextField
                                id="brand_name"
                                label="Brand name"
                                value={form.data.brand_name}
                                onChange={(value) =>
                                    form.setData('brand_name', value)
                                }
                                error={errors.brand_name}
                            />
                            <TextField
                                id="strength"
                                label="Strength"
                                placeholder="500 mg"
                                value={form.data.strength}
                                onChange={(value) =>
                                    form.setData('strength', value)
                                }
                                error={errors.strength}
                            />
                            <SelectField
                                id="dosage_form_id"
                                label="Dosage form"
                                value={form.data.dosage_form_id}
                                options={dosageForms}
                                onChange={(value) =>
                                    form.setData('dosage_form_id', value)
                                }
                                error={errors.dosage_form_id}
                            />
                        </div>
                        <div className="grid gap-6 sm:grid-cols-3">
                            <Field
                                label="Manufacturer"
                                htmlFor="manufacturer"
                                error={errors.manufacturer}
                            >
                                <Input
                                    id="manufacturer"
                                    list="manufacturers"
                                    value={form.data.manufacturer}
                                    onChange={(event) =>
                                        form.setData(
                                            'manufacturer',
                                            event.target.value,
                                        )
                                    }
                                />
                                <datalist id="manufacturers">
                                    {manufacturers.map((name) => (
                                        <option key={name} value={name} />
                                    ))}
                                </datalist>
                            </Field>
                            <SelectField
                                id="category_id"
                                label="Category"
                                value={form.data.category_id}
                                options={categories}
                                onChange={(value) =>
                                    form.setData('category_id', value)
                                }
                                error={errors.category_id}
                            />
                            <TextField
                                id="fda_registration_number"
                                label="FDA registration no."
                                value={form.data.fda_registration_number}
                                onChange={(value) =>
                                    form.setData(
                                        'fda_registration_number',
                                        value,
                                    )
                                }
                                error={errors.fda_registration_number}
                            />
                        </div>
                    </section>

                    <section className="space-y-6">
                        <Heading
                            variant="small"
                            title="Classification and tax"
                        />
                        <div className="grid gap-6 sm:grid-cols-3">
                            <SelectField
                                id="classification"
                                label="Classification"
                                value={form.data.classification}
                                options={classifications}
                                onChange={(value) =>
                                    form.setData('classification', value)
                                }
                                error={errors.classification}
                                required
                            />
                            <SelectField
                                id="tax_class"
                                label="Tax class"
                                value={form.data.tax_class}
                                options={taxClasses}
                                onChange={(value) =>
                                    form.setData('tax_class', value)
                                }
                                error={errors.tax_class}
                                required
                            />
                            <TextField
                                id="tax_class_effective_from"
                                label="Tax class effective from"
                                type="date"
                                value={form.data.tax_class_effective_from}
                                onChange={(value) =>
                                    form.setData(
                                        'tax_class_effective_from',
                                        value,
                                    )
                                }
                                error={errors.tax_class_effective_from}
                            />
                        </div>
                        <CheckboxField
                            id="statutory_discount_eligible"
                            label="Senior citizen and PWD discount applies"
                            help="Medicines qualify; most non-drug items do not."
                            checked={form.data.statutory_discount_eligible}
                            onChange={(value) =>
                                form.setData(
                                    'statutory_discount_eligible',
                                    value,
                                )
                            }
                        />
                    </section>

                    <section className="space-y-4">
                        <div className="flex items-end justify-between">
                            <Heading
                                variant="small"
                                title="Units and prices"
                                description="Stock is counted in the base unit. Conversion is how many base units one of the unit holds."
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    form.setData('units', [
                                        ...form.data.units,
                                        emptyUnit(),
                                    ])
                                }
                            >
                                <Plus /> Add unit
                            </Button>
                        </div>
                        <InputError message={errors.units} />
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Unit</TableHead>
                                        <TableHead>Conversion</TableHead>
                                        <TableHead>Base</TableHead>
                                        <TableHead>Sell</TableHead>
                                        <TableHead>Buy</TableHead>
                                        <TableHead>Price (₱)</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {form.data.units.map((unit, i) => (
                                        <TableRow key={unit.id ?? `new-${i}`}>
                                            <TableCell>
                                                <Input
                                                    aria-label="Unit name"
                                                    value={unit.name}
                                                    onChange={(event) =>
                                                        setUnit(i, {
                                                            name: event.target
                                                                .value,
                                                        })
                                                    }
                                                    className="w-32"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `units.${i}.name`
                                                        ]
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    aria-label="Conversion"
                                                    inputMode="numeric"
                                                    value={
                                                        unit.is_base
                                                            ? 1
                                                            : unit.conversion_factor
                                                    }
                                                    disabled={unit.is_base}
                                                    onChange={(event) =>
                                                        setUnit(i, {
                                                            conversion_factor:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    className="w-24"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `units.${i}.conversion_factor`
                                                        ]
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Checkbox
                                                    aria-label="Base unit"
                                                    checked={unit.is_base}
                                                    onCheckedChange={(value) =>
                                                        setUnit(i, {
                                                            is_base:
                                                                value === true,
                                                            conversion_factor:
                                                                value === true
                                                                    ? 1
                                                                    : unit.conversion_factor,
                                                        })
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Checkbox
                                                    aria-label="Sellable"
                                                    checked={unit.is_sellable}
                                                    onCheckedChange={(value) =>
                                                        setUnit(i, {
                                                            is_sellable:
                                                                value === true,
                                                        })
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Checkbox
                                                    aria-label="Purchasable"
                                                    checked={
                                                        unit.is_purchasable
                                                    }
                                                    onCheckedChange={(value) =>
                                                        setUnit(i, {
                                                            is_purchasable:
                                                                value === true,
                                                        })
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    aria-label="Price"
                                                    inputMode="decimal"
                                                    value={unit.price ?? ''}
                                                    disabled={!canSetPrices}
                                                    onChange={(event) =>
                                                        setUnit(i, {
                                                            price: event.target
                                                                .value,
                                                        })
                                                    }
                                                    className="w-28"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `units.${i}.price`
                                                        ]
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {!unit.is_base && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Remove unit"
                                                        onClick={() =>
                                                            form.setData(
                                                                'units',
                                                                form.data.units.filter(
                                                                    (_, j) =>
                                                                        j !== i,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {!canSetPrices && (
                            <p className="text-xs text-muted-foreground">
                                Prices can only be changed by users with
                                permission to set prices.
                            </p>
                        )}
                    </section>

                    <section className="space-y-4">
                        <div className="flex items-end justify-between">
                            <Heading
                                variant="small"
                                title="Barcodes"
                                description="Scan into a code box; Enter adds another row."
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addBarcode}
                            >
                                <Plus /> Add barcode
                            </Button>
                        </div>
                        {form.data.barcodes.map((barcode, i) => (
                            <div key={i} className="flex items-start gap-2">
                                <div className="flex-1">
                                    <Input
                                        aria-label="Barcode"
                                        value={barcode.code}
                                        autoFocus={
                                            i ===
                                                form.data.barcodes.length - 1 &&
                                            barcode.code === ''
                                        }
                                        onKeyDown={onBarcodeKeyDown}
                                        onChange={(event) =>
                                            setBarcode(i, {
                                                code: event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors[`barcodes.${i}.code`]}
                                    />
                                </div>
                                <select
                                    aria-label="Unit scanned"
                                    className="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                                    value={barcode.unit}
                                    onChange={(event) =>
                                        setBarcode(i, {
                                            unit: event.target.value,
                                        })
                                    }
                                >
                                    {form.data.units
                                        .filter((unit) => unit.name)
                                        .map((unit) => (
                                            <option
                                                key={unit.name}
                                                value={unit.name}
                                            >
                                                {unit.name}
                                            </option>
                                        ))}
                                </select>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove barcode"
                                    onClick={() =>
                                        form.setData(
                                            'barcodes',
                                            form.data.barcodes.filter(
                                                (_, j) => j !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </section>

                    <section className="space-y-6">
                        <Heading variant="small" title="Stock and storage" />
                        <div className="grid gap-6 sm:grid-cols-3">
                            <TextField
                                id="reorder_point"
                                label={`Reorder point (${baseUnit?.name || 'base units'})`}
                                inputMode="numeric"
                                value={form.data.reorder_point}
                                onChange={(value) =>
                                    form.setData('reorder_point', value)
                                }
                                error={errors.reorder_point}
                                required
                            />
                            <TextField
                                id="max_level"
                                label={`Maximum level (${baseUnit?.name || 'base units'})`}
                                inputMode="numeric"
                                value={form.data.max_level}
                                onChange={(value) =>
                                    form.setData('max_level', value)
                                }
                                error={errors.max_level}
                            />
                            <SelectField
                                id="storage_condition"
                                label="Storage"
                                value={form.data.storage_condition}
                                options={storageConditions}
                                onChange={(value) =>
                                    form.setData('storage_condition', value)
                                }
                                error={errors.storage_condition}
                            />
                        </div>
                        <Field
                            label="Notes"
                            htmlFor="description"
                            error={errors.description}
                        >
                            <Textarea
                                id="description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <CheckboxField
                            id="is_active"
                            label="Active"
                            help="Inactive products cannot be sold or ordered."
                            checked={form.data.is_active}
                            onChange={(value) =>
                                form.setData('is_active', value)
                            }
                        />
                    </section>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {product ? 'Save product' : 'Create product'}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Back</Link>
                        </Button>
                    </div>
                </form>

                {priceHistory.length > 0 && (
                    <section className="mt-12 max-w-4xl space-y-4">
                        <Heading variant="small" title="Price history" />
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Unit</TableHead>
                                        <TableHead className="text-right">
                                            Old
                                        </TableHead>
                                        <TableHead className="text-right">
                                            New
                                        </TableHead>
                                        <TableHead>By</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {priceHistory.map((change) => (
                                        <TableRow key={change.id}>
                                            <TableCell>
                                                {formatDateTime(
                                                    change.changed_at,
                                                )}
                                            </TableCell>
                                            <TableCell>{change.unit}</TableCell>
                                            <TableCell className="text-right">
                                                {formatMoney(change.old_price)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatMoney(change.new_price)}
                                            </TableCell>
                                            <TableCell>
                                                {change.changed_by ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

ProductForm.layout = {
    breadcrumbs: [{ title: 'Products', href: index() }],
};
