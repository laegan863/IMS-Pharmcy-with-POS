import { Search } from 'lucide-react';
import { forwardRef, useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { products as productLookup } from '@/routes/catalog/lookup';
import type { LookupProduct } from '@/types/product';

type ProductPickerProps = {
    /** Called with the chosen product and the unit scanned, if any. */
    onPick: (product: LookupProduct, unitId: number | null) => void;
    placeholder?: string;
    autoFocus?: boolean;
    className?: string;
};

async function search(term: string): Promise<LookupProduct[]> {
    const response = await fetch(productLookup({ query: { q: term } }).url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return [];
    }

    const body = (await response.json()) as { data: LookupProduct[] };

    return body.data;
}

/**
 * Scan or search a product without the mouse. A barcode scanner types the
 * code and presses Enter: an exact barcode match is picked at once with its
 * unit. Typing a name lists matches; arrow keys move, Enter picks, Escape
 * clears. Focus stays in the box so the next scan can follow straight away.
 */
export const ProductPicker = forwardRef<HTMLInputElement, ProductPickerProps>(
    function ProductPicker(
        {
            onPick,
            placeholder = 'Scan a barcode or type a name…',
            autoFocus,
            className,
        },
        ref,
    ) {
        const [term, setTerm] = useState('');
        const [results, setResults] = useState<LookupProduct[]>([]);
        const [active, setActive] = useState(0);
        const [loading, setLoading] = useState(false);
        const [notFound, setNotFound] = useState(false);
        const request = useRef(0);

        useEffect(() => {
            const trimmed = term.trim();

            if (trimmed.length < 2) {
                setResults([]);

                return;
            }

            const id = ++request.current;
            const timer = window.setTimeout(async () => {
                setLoading(true);
                const found = await search(trimmed);

                if (id === request.current) {
                    setResults(found);
                    setActive(0);
                    setLoading(false);
                }
            }, 200);

            return () => window.clearTimeout(timer);
        }, [term]);

        const pick = (product: LookupProduct) => {
            onPick(product, product.scanned_unit_id);
            setTerm('');
            setResults([]);
            setNotFound(false);
            request.current++;
        };

        const onKeyDown = async (event: KeyboardEvent<HTMLInputElement>) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive((index) => Math.min(index + 1, results.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive((index) => Math.max(index - 1, 0));
            } else if (event.key === 'Escape') {
                setTerm('');
                setResults([]);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const trimmed = term.trim();

                if (results[active] && results.length > 0) {
                    pick(results[active]);

                    return;
                }

                if (trimmed === '') {
                    return;
                }

                // A scanner is faster than the debounce: look up now.
                request.current++;
                setLoading(true);
                const found = await search(trimmed);
                setLoading(false);

                if (found.length === 1) {
                    pick(found[0]);
                } else if (found.length === 0) {
                    setNotFound(true);
                } else {
                    setResults(found);
                    setActive(0);
                }
            }
        };

        return (
            <div className={cn('relative', className)}>
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    ref={ref}
                    value={term}
                    onChange={(event) => {
                        setTerm(event.target.value);
                        setNotFound(false);
                    }}
                    onKeyDown={onKeyDown}
                    placeholder={placeholder}
                    autoFocus={autoFocus}
                    autoComplete="off"
                    className="pl-8"
                    role="combobox"
                    aria-expanded={results.length > 0}
                    aria-controls="product-picker-results"
                    aria-label="Product"
                />
                {loading && (
                    <Spinner className="absolute top-1/2 right-2.5 -translate-y-1/2" />
                )}
                {notFound && (
                    <p className="mt-1 text-xs text-destructive">
                        No product matches “{term}”.
                    </p>
                )}
                {results.length > 0 && (
                    <ul
                        id="product-picker-results"
                        role="listbox"
                        className="absolute z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-sm shadow-md"
                    >
                        {results.map((product, index) => (
                            <li
                                key={product.id}
                                role="option"
                                aria-selected={index === active}
                                className={cn(
                                    'cursor-pointer rounded-sm px-2 py-1.5',
                                    index === active &&
                                        'bg-accent text-accent-foreground',
                                )}
                                onMouseEnter={() => setActive(index)}
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    pick(product);
                                }}
                            >
                                <div className="font-medium">
                                    {product.name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {product.sku}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        );
    },
);
