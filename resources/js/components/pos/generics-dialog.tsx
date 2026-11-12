import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatMoney } from '@/lib/format';
import { http } from '@/lib/http';
import { alternatives as alternativesRoute } from '@/routes/pos/rx';

export type Alternative = {
    id: number;
    name: string;
    same_strength: boolean;
    base_unit_id: number;
    base_unit: string;
    base_price: string;
    available: number;
};

/**
 * Other products with the same generic name, cheapest first, so the cashier
 * can tell the customer about generic equivalents (Generics Act).
 */
export function GenericsDialog({
    open,
    productId,
    productName,
    onClose,
    onSwap,
}: {
    open: boolean;
    productId: number;
    productName: string;
    onClose: () => void;
    onSwap: (alternative: Alternative) => void;
}) {
    const [alternatives, setAlternatives] = useState<Alternative[] | null>(
        null,
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        setAlternatives(null);
        void http
            .get<{ data: Alternative[] }>(
                alternativesRoute({ query: { product_id: productId } }).url,
            )
            .then((result) => setAlternatives(result.data));
    }, [open, productId]);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Generic equivalents</DialogTitle>
                    <DialogDescription>
                        Same active ingredient as {productName}. Prices are per
                        base unit.
                    </DialogDescription>
                </DialogHeader>
                {alternatives?.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No other products with this generic name.
                    </p>
                )}
                <div className="space-y-2">
                    {alternatives?.map((alternative) => (
                        <div
                            key={alternative.id}
                            className="flex items-center justify-between gap-3 rounded-md border p-2 text-sm"
                        >
                            <div>
                                <div className="font-medium">
                                    {alternative.name}
                                </div>
                                <div className="flex gap-1 text-xs text-muted-foreground">
                                    {formatMoney(alternative.base_price)} per{' '}
                                    {alternative.base_unit} ·{' '}
                                    {alternative.available} in stock
                                    {!alternative.same_strength && (
                                        <StatusBadge tone="warning">
                                            Different strength
                                        </StatusBadge>
                                    )}
                                </div>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={alternative.available === 0}
                                onClick={() => onSwap(alternative)}
                            >
                                Use this
                            </Button>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
