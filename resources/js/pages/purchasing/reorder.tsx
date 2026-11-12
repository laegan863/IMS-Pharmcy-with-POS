import { Head, router } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import { SelectField } from '@/components/form/select-field';
import type { Option } from '@/components/form/select-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney, formatNumber } from '@/lib/format';
import { reorder } from '@/routes/purchasing';
import { store } from '@/routes/purchasing/orders';

type Suggestion = {
    product_id: number;
    sku: string;
    name: string;
    on_hand: number;
    on_order: number;
    reorder_point: number;
    sold_30_days: number;
    product_unit_id: number;
    unit: string;
    conversion_factor: number;
    quantity: number;
    unit_cost: string;
};

export default function Reorder({
    suggestions,
    suppliers,
}: {
    suggestions: Suggestion[];
    suppliers: Option[];
}) {
    const [rows, setRows] = useState(
        suggestions.map((suggestion) => ({ ...suggestion, selected: true })),
    );
    const [supplierId, setSupplierId] = useState('');
    const [processing, setProcessing] = useState(false);
    const selected = rows.filter((row) => row.selected && row.quantity > 0);

    const createOrder = () => {
        setProcessing(true);
        router.post(
            store().url,
            {
                supplier_id: supplierId,
                lines: selected.map((row) => ({
                    product_id: row.product_id,
                    product_unit_id: row.product_unit_id,
                    quantity: row.quantity,
                    unit_cost: row.unit_cost,
                })),
            },
            { onFinish: () => setProcessing(false) },
        );
    };

    return (
        <>
            <Head title="Reorder list" />
            <div className="px-4 py-6">
                <PageHeader
                    title="Reorder list"
                    description="Products at or below their reorder point. Quantities aim for the maximum level, or 30 days of recent sales, less stock on hand and on open orders."
                />
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <SelectField
                        id="supplier"
                        label="Order from"
                        value={supplierId}
                        options={suppliers}
                        onChange={setSupplierId}
                        className="sm:w-72"
                    />
                    <Button
                        disabled={
                            !supplierId || selected.length === 0 || processing
                        }
                        onClick={createOrder}
                    >
                        <ShoppingCart /> Create draft order ({selected.length})
                    </Button>
                </div>
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10" />
                                <TableHead>Product</TableHead>
                                <TableHead className="text-right">
                                    On hand
                                </TableHead>
                                <TableHead className="text-right">
                                    On order
                                </TableHead>
                                <TableHead className="text-right">
                                    Reorder at
                                </TableHead>
                                <TableHead className="text-right">
                                    Sold 30 days
                                </TableHead>
                                <TableHead>Order</TableHead>
                                <TableHead className="text-right">
                                    Last cost
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="h-16 text-center text-muted-foreground"
                                    >
                                        Nothing needs reordering.
                                    </TableCell>
                                </TableRow>
                            )}
                            {rows.map((row, index) => (
                                <TableRow key={row.product_id}>
                                    <TableCell>
                                        <Checkbox
                                            aria-label={`Include ${row.name}`}
                                            checked={row.selected}
                                            onCheckedChange={(value) =>
                                                setRows(
                                                    rows.map((r, i) =>
                                                        i === index
                                                            ? {
                                                                  ...r,
                                                                  selected:
                                                                      value ===
                                                                      true,
                                                              }
                                                            : r,
                                                    ),
                                                )
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        <div className="font-medium">
                                            {row.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.sku}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatNumber(row.on_hand)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatNumber(row.on_order)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatNumber(row.reorder_point)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatNumber(row.sold_30_days)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Input
                                                aria-label="Quantity to order"
                                                inputMode="numeric"
                                                className="w-20"
                                                value={row.quantity}
                                                onChange={(event) =>
                                                    setRows(
                                                        rows.map((r, i) =>
                                                            i === index
                                                                ? {
                                                                      ...r,
                                                                      quantity:
                                                                          Number(
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                          ) ||
                                                                          0,
                                                                  }
                                                                : r,
                                                        ),
                                                    )
                                                }
                                            />
                                            <span className="text-sm">
                                                {row.unit}
                                                {row.conversion_factor > 1 &&
                                                    ` × ${row.conversion_factor}`}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(row.unit_cost)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

Reorder.layout = {
    breadcrumbs: [{ title: 'Reorder list', href: reorder() }],
};
