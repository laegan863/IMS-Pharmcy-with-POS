import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Column, Paginated, TableFilter, TableState } from '@/types/table';

type DataTableProps<T> = {
    rows: Paginated<T>;
    state: TableState;
    columns: Column<T>[];
    rowKey: (row: T) => string | number;
    filters?: TableFilter[];
    searchPlaceholder?: string;
    /** Shown when there are no rows. */
    empty?: ReactNode;
    /** Buttons shown to the right of the search box. */
    actions?: ReactNode;
    onRowClick?: (row: T) => void;
};

const ALL = '__all';

/**
 * A list with search, filters, sorting and paging done on the server by
 * App\Support\TableQuery. State lives in the query string, so a filtered
 * list can be bookmarked and the back button works.
 */
export function DataTable<T>({
    rows,
    state,
    columns,
    rowKey,
    filters = [],
    searchPlaceholder = 'Search…',
    empty = 'Nothing to show.',
    actions,
    onRowClick,
}: DataTableProps<T>) {
    const [search, setSearch] = useState(state.search);
    const firstRender = useRef(true);

    const visit = (changes: Partial<TableState> & { page?: number }) => {
        const next = { ...state, ...changes };
        const query: Record<string, string | number> = {
            sort: next.sort,
            direction: next.direction,
            per_page: next.per_page,
        };

        if (next.search) {
            query.search = next.search;
        }

        if (changes.page && changes.page > 1) {
            query.page = changes.page;
        }

        for (const [name, value] of Object.entries(next.filters)) {
            if (value) {
                query[`filter[${name}]`] = value;
            }
        }

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Search after the user stops typing for 300 ms.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = window.setTimeout(() => {
            if (search !== state.search) {
                visit({ search });
            }
        }, 300);

        return () => window.clearTimeout(timer);
    }, [search]);

    const toggleSort = (key: string) => {
        const direction =
            state.sort === key && state.direction === 'asc' ? 'desc' : 'asc';
        visit({ sort: key, direction });
    };

    const alignClass = (align: Column<T>['align']) =>
        align === 'right'
            ? 'text-right'
            : align === 'center'
              ? 'text-center'
              : 'text-left';

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative w-full sm:max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={searchPlaceholder}
                        className="pl-8"
                        aria-label="Search"
                    />
                </div>

                {filters.map((filter) => (
                    <Select
                        key={filter.name}
                        value={state.filters[filter.name] ?? ALL}
                        onValueChange={(value) =>
                            visit({
                                filters: {
                                    ...state.filters,
                                    [filter.name]: value === ALL ? '' : value,
                                },
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label={filter.label}
                        >
                            <SelectValue placeholder={filter.label} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                All {filter.label.toLowerCase()}
                            </SelectItem>
                            {filter.options.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ))}

                {actions && (
                    <div className="flex gap-2 sm:ml-auto">{actions}</div>
                )}
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={cn(
                                        alignClass(column.align),
                                        column.className,
                                    )}
                                    aria-sort={
                                        state.sort === column.key
                                            ? state.direction === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : undefined
                                    }
                                >
                                    {column.sortable ? (
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 hover:text-foreground"
                                            onClick={() =>
                                                toggleSort(column.key)
                                            }
                                        >
                                            {column.header}
                                            {state.sort !== column.key ? (
                                                <ArrowUpDown className="size-3.5 opacity-50" />
                                            ) : state.direction === 'asc' ? (
                                                <ArrowUp className="size-3.5" />
                                            ) : (
                                                <ArrowDown className="size-3.5" />
                                            )}
                                        </button>
                                    ) : (
                                        column.header
                                    )}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center text-muted-foreground"
                                >
                                    {empty}
                                </TableCell>
                            </TableRow>
                        ) : (
                            rows.data.map((row) => (
                                <TableRow
                                    key={rowKey(row)}
                                    className={cn(
                                        onRowClick && 'cursor-pointer',
                                    )}
                                    onClick={
                                        onRowClick
                                            ? () => onRowClick(row)
                                            : undefined
                                    }
                                >
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn(
                                                alignClass(column.align),
                                                column.className,
                                            )}
                                        >
                                            {column.cell(row)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex flex-col items-center justify-between gap-2 text-sm text-muted-foreground sm:flex-row">
                <span>
                    {rows.total === 0
                        ? 'No results'
                        : `Showing ${rows.from}–${rows.to} of ${rows.total}`}
                </span>
                <div className="flex items-center gap-2">
                    <Select
                        value={String(state.per_page)}
                        onValueChange={(value) =>
                            visit({ per_page: Number(value) })
                        }
                    >
                        <SelectTrigger
                            className="h-8 w-20"
                            aria-label="Rows per page"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[10, 25, 50, 100].map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={rows.current_page <= 1}
                        onClick={() => visit({ page: rows.current_page - 1 })}
                        aria-label="Previous page"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span>
                        Page {rows.current_page} of {rows.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={rows.current_page >= rows.last_page}
                        onClick={() => visit({ page: rows.current_page + 1 })}
                        aria-label="Next page"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
