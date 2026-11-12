<?php

namespace App\Http\Controllers\Purchasing;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\ReorderSuggestions;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(PurchaseOrder::query()->with('supplier:id,name'), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%")))
            ->sortable(['number', 'created_at', 'expected_on'])
            ->defaultSort('created_at', 'desc')
            ->filter('status', fn (Builder $query, string $value) => $query->where('status', $value))
            ->paginate(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'supplier' => $order->supplier->name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'expected_on' => $order->expected_on?->toDateString(),
                'total' => $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        return Inertia::render('purchasing/orders/index', [
            ...$table,
            'statuses' => array_map(fn (PurchaseOrderStatus $status) => ['value' => $status->value, 'label' => $status->label()], PurchaseOrderStatus::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('purchasing/orders/form', ['order' => null, 'suppliers' => $this->suppliers()]);
    }

    public function store(Request $request, PurchaseOrderService $orders): RedirectResponse
    {
        $order = $orders->saveDraft(null, $this->branchId($request), ...$this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order :number saved as draft.', ['number' => $order->number])]);

        return to_route('purchasing.orders.show', $order);
    }

    public function show(Request $request, PurchaseOrder $order): Response
    {
        $order->load(['supplier', 'lines.product.genericName', 'lines.product.dosageForm', 'lines.product.units', 'lines.unit', 'creator:id,name', 'approver:id,name']);
        $user = $request->user();

        return Inertia::render('purchasing/orders/form', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'supplier_id' => $order->supplier_id,
                'supplier' => $order->supplier->name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'expected_on' => $order->expected_on?->toDateString(),
                'notes' => $order->notes,
                'total' => $order->total,
                'created_by' => $order->creator?->name,
                'approved_by' => $order->approver?->name,
                'approved_at' => $order->approved_at?->toIso8601String(),
                'lines' => $order->lines->map(fn (PurchaseOrderLine $line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product->displayName(),
                    'product_unit_id' => $line->product_unit_id,
                    'unit' => $line->unit->name,
                    'units' => $line->product->units->where('is_purchasable', true)->map(fn ($unit) => ['id' => $unit->id, 'name' => $unit->name, 'conversion_factor' => $unit->conversion_factor])->values(),
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'line_total' => $line->line_total,
                    'received' => intdiv($line->received_base, max(1, $line->unit->conversion_factor)),
                ])->values(),
            ],
            'suppliers' => $this->suppliers(),
            'can' => [
                'edit' => $order->status === PurchaseOrderStatus::Draft && ($user?->can(Permission::PurchaseOrdersManage->value) ?? false),
                'approve' => $order->status === PurchaseOrderStatus::Draft && ($user?->can(Permission::PurchaseOrdersApprove->value) ?? false),
                'cancel' => in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true) && ($user?->can(Permission::PurchaseOrdersManage->value) ?? false),
                'receive' => $order->status->isReceivable() && ($user?->can(Permission::ReceiptsManage->value) ?? false),
            ],
        ]);
    }

    public function update(Request $request, PurchaseOrder $order, PurchaseOrderService $orders): RedirectResponse
    {
        $orders->saveDraft($order, $order->branch_id, ...$this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved.')]);

        return to_route('purchasing.orders.show', $order);
    }

    public function approve(Request $request, PurchaseOrder $order, PurchaseOrderService $orders): RedirectResponse
    {
        $orders->approve($order, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order approved.')]);

        return to_route('purchasing.orders.show', $order);
    }

    public function cancel(PurchaseOrder $order, PurchaseOrderService $orders): RedirectResponse
    {
        $orders->cancel($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled.')]);

        return to_route('purchasing.orders.show', $order);
    }

    public function reorder(Request $request, ReorderSuggestions $suggestions): Response
    {
        return Inertia::render('purchasing/reorder', [
            'suggestions' => $suggestions->forBranch($this->branchId($request)),
            'suppliers' => $this->suppliers(),
        ]);
    }

    /**
     * @return array{0: array{supplier_id: int, expected_on: string|null, notes: string|null}, 1: list<array{product_id: int, product_unit_id: int, quantity: int, unit_cost: string}>, 2: User}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.product_unit_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        return [
            ['supplier_id' => (int) $data['supplier_id'], 'expected_on' => $data['expected_on'] ?? null, 'notes' => $data['notes'] ?? null],
            array_values(array_map(fn (array $line) => [
                'product_id' => (int) $line['product_id'],
                'product_unit_id' => (int) $line['product_unit_id'],
                'quantity' => (int) $line['quantity'],
                'unit_cost' => (string) $line['unit_cost'],
            ], $data['lines'])),
            $this->user($request),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function suppliers(): array
    {
        return array_values(Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Supplier $supplier) => ['value' => (string) $supplier->id, 'label' => $supplier->name])->all());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function branchId(Request $request): int
    {
        $branchId = $this->user($request)->branch_id;
        abort_if($branchId === null, 403, __('Your account has no branch.'));

        return $branchId;
    }
}
