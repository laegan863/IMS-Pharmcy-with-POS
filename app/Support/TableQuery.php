<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Server-side search, sort, filter and paging for list pages. Pairs with the
 * <DataTable> React component, which sends the same query-string keys:
 * search, sort, direction, page, per_page and filter[name].
 *
 * Sorting is limited to the columns given to sortable(), so the query string
 * can never reach an arbitrary column.
 *
 * @template TModel of Model
 */
class TableQuery
{
    /** @var (Closure(Builder<TModel>, string): mixed)|null */
    private ?Closure $search = null;

    /** @var array<string, string|Closure(Builder<TModel>, string): mixed> */
    private array $sortable = [];

    /** @var array<string, Closure(Builder<TModel>, string): mixed> */
    private array $filters = [];

    private string $defaultSort = 'id';

    private string $defaultDirection = 'asc';

    /**
     * @param  Builder<TModel>  $query
     */
    private function __construct(private Builder $query, private Request $request) {}

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return self<T>
     */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * @param  Closure(Builder<TModel>, string): mixed  $callback
     * @return $this
     */
    public function searchUsing(Closure $callback): self
    {
        $this->search = $callback;

        return $this;
    }

    /**
     * Allowed sort keys. A plain list maps each key to the column of the same
     * name; a key => column (or key => closure) map allows aliases.
     *
     * @param  array<int|string, string|Closure(Builder<TModel>, string): mixed>  $columns
     * @return $this
     */
    public function sortable(array $columns): self
    {
        foreach ($columns as $key => $column) {
            $this->sortable[is_int($key) && is_string($column) ? $column : (string) $key] = $column;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function defaultSort(string $key, string $direction = 'asc'): self
    {
        $this->defaultSort = $key;
        $this->defaultDirection = $direction === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /**
     * @param  Closure(Builder<TModel>, string): mixed  $callback
     * @return $this
     */
    public function filter(string $name, Closure $callback): self
    {
        $this->filters[$name] = $callback;

        return $this;
    }

    /**
     * Runs the query and returns the page plus the table state for the page.
     *
     * @param  Closure(TModel): array<string, mixed>  $transform
     * @return array{rows: LengthAwarePaginator<int, array<string, mixed>>, state: array{search: string, sort: string, direction: string, per_page: int, filters: array<string, string>}}
     */
    public function paginate(Closure $transform): array
    {
        $search = trim($this->string('search'));
        $sort = $this->string('sort', $this->defaultSort);
        $sort = array_key_exists($sort, $this->sortable) ? $sort : $this->defaultSort;
        $direction = $this->string('direction', $this->defaultDirection) === 'desc' ? 'desc' : 'asc';
        $perPage = in_array($this->request->integer('per_page'), [10, 25, 50, 100], true) ? $this->request->integer('per_page') : 25;
        $filters = [];

        if ($search !== '' && $this->search !== null) {
            $this->query->where(fn (Builder $query) => ($this->search)($query, $search));
        }

        foreach ($this->filters as $name => $callback) {
            $value = $this->request->input("filter.{$name}");

            if (is_scalar($value) && (string) $value !== '') {
                $filters[$name] = (string) $value;
                $callback($this->query, (string) $value);
            }
        }

        if (array_key_exists($sort, $this->sortable)) {
            $column = $this->sortable[$sort];
            $column instanceof Closure
                ? $column($this->query, $direction)
                : $this->query->orderBy($column, $direction);
        }

        // A stable tiebreaker keeps rows from jumping between pages.
        $this->query->orderBy($this->query->getModel()->getQualifiedKeyName(), $direction);

        $page = $this->query->paginate($perPage)->withQueryString();

        return [
            'rows' => $page->through($transform),
            'state' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'filters' => $filters,
            ],
        ];
    }

    private function string(string $key, string $default = ''): string
    {
        $value = $this->request->input($key);

        return is_string($value) ? $value : $default;
    }
}
