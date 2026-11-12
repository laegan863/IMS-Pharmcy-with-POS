<?php

namespace App\Http\Controllers\Purchasing;

use App\Enums\DocumentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\GoodsReceiptService;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(GoodsReceipt::query()->with(['supplier:id,name', 'purchaseOrder:id,number']), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhere('supplier_invoice_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%")))
            ->sortable(['number', 'created_at', 'posted_at'])
            ->defaultSort('created_at', 'desc')
            ->filter('status', fn (Builder $query, string $value) => $query->where('status', $value))
            ->paginate(fn (GoodsReceipt $receipt) => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'supplier' => $receipt->supplier->name,
                'supplier_invoice_number' => $receipt->supplier_invoice_number,
                'purchase_order' => $receipt->purchaseOrder?->number,
                'status' => $receipt->status->value,
                'total' => $receipt->total,
                'posted_at' => $receipt->posted_at?->toIso8601String(),
                'created_at' => $receipt->created_at?->toIso8601String(),
            ]);

        return Inertia::render('purchasing/receipts/index', $table);
    }

    public function create(Request $request): Response
    {
        $order = $request->filled('purchase_order')
            ? PurchaseOrder::query()->with(['lines.product.genericName', 'lines.product.dosageForm', 'lines.product.units', 'lines.unit'])->findOrFail($request->integer('purchase_order'))
            : null;

        abort_if($order !== null && ! $order->status->isReceivable(), 422, __('This order cannot be received.'));

        return Inertia::render('purchasing/receipts/form', [
            'receipt' => $order === null ? null : [
                'id' => null,
                'number' => null,
                'status' => 'draft',
                'supplier_id' => $order->supplier_id,
                'purchase_order_id' => $order->id,
                'purchase_order_number' => $order->number,
                'supplier_invoice_number' => '',
                'supplier_invoice_date' => '',
                'notes' => '',
                'lines' => $order->lines
                    ->filter(fn (PurchaseOrderLine $line) => $line->outstandingBase() > 0)
                    ->map(fn (PurchaseOrderLine $line) => [
                        'purchase_order_line_id' => $line->id,
                        'product_id' => $line->product_id,
                        'product_name' => $line->product->displayName(),
                        'product_unit_id' => $line->product_unit_id,
                        'units' => $this->unitOptions($line->product->units),
                        'lot_number' => '',
                        'expires_on' => '',
                        'quantity' => intdiv($line->outstandingBase(), max(1, $line->unit->conversion_factor)),
                        'free_quantity' => 0,
                        'unit_cost' => $line->unit_cost,
                    ])->values(),
            ],
            'suppliers' => $this->suppliers(),
            'warnings' => [],
        ]);
    }

    public function store(Request $request, GoodsReceiptService $receipts): RedirectResponse
    {
        [$header, $lines] = $this->validated($request);
        $receipt = $receipts->saveDraft(null, $this->branchId($request), $header, $lines, $this->user($request));

        return $this->afterSave($request, $receipt, $receipts);
    }

    public function show(GoodsReceipt $receipt, GoodsReceiptService $receipts): Response
    {
        $receipt->load(['supplier', 'purchaseOrder', 'lines.product.genericName', 'lines.product.dosageForm', 'lines.product.units', 'lines.unit', 'poster:id,name']);

        return Inertia::render('purchasing/receipts/form', [
            'receipt' => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'status' => $receipt->status->value,
                'supplier_id' => $receipt->supplier_id,
                'supplier' => $receipt->supplier->name,
                'purchase_order_id' => $receipt->purchase_order_id,
                'purchase_order_number' => $receipt->purchaseOrder?->number,
                'supplier_invoice_number' => $receipt->supplier_invoice_number ?? '',
                'supplier_invoice_date' => $receipt->supplier_invoice_date?->toDateString() ?? '',
                'notes' => $receipt->notes ?? '',
                'total' => $receipt->total,
                'posted_by' => $receipt->poster?->name,
                'posted_at' => $receipt->posted_at?->toIso8601String(),
                'lines' => $receipt->lines->map(fn (GoodsReceiptLine $line) => [
                    'purchase_order_line_id' => $line->purchase_order_line_id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product->displayName(),
                    'product_unit_id' => $line->product_unit_id,
                    'units' => $this->unitOptions($line->product->units),
                    'lot_number' => $line->lot_number,
                    'expires_on' => $line->expires_on->toDateString(),
                    'quantity' => $line->quantity,
                    'free_quantity' => $line->free_quantity,
                    'unit_cost' => $line->unit_cost,
                    'batch_id' => $line->batch_id,
                ])->values(),
            ],
            'suppliers' => $this->suppliers(),
            'warnings' => $receipt->status === DocumentStatus::Draft ? $receipts->warnings($receipt) : [],
        ]);
    }

    public function update(Request $request, GoodsReceipt $receipt, GoodsReceiptService $receipts): RedirectResponse
    {
        [$header, $lines] = $this->validated($request);
        $receipt = $receipts->saveDraft($receipt, $receipt->branch_id, $header, $lines, $this->user($request));

        return $this->afterSave($request, $receipt, $receipts);
    }

    /**
     * Saves the draft; posts it too when the user pressed Post.
     */
    private function afterSave(Request $request, GoodsReceipt $receipt, GoodsReceiptService $receipts): RedirectResponse
    {
        if ($request->boolean('post')) {
            try {
                $receipts->post($receipt, $this->user($request), $request->boolean('acknowledge_warnings'));
            } catch (ValidationException $exception) {
                // The draft is saved; show it with what stopped the posting.
                return to_route('purchasing.receipts.show', $receipt)->withErrors($exception->errors());
            }

            $receipt->refresh();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Receipt :number posted. Stock updated.', ['number' => $receipt->number])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved.')]);
        }

        return to_route('purchasing.receipts.show', $receipt);
    }

    /**
     * @return array{0: array{supplier_id: int, purchase_order_id: int|null, supplier_invoice_number: string|null, supplier_invoice_date: string|null, notes: string|null}, 1: list<array{purchase_order_line_id: int|null, product_id: int, product_unit_id: int, lot_number: string, expires_on: string, quantity: int, free_quantity: int, unit_cost: string}>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->whereIn('status', [PurchaseOrderStatus::Approved->value, PurchaseOrderStatus::PartlyReceived->value])],
            'supplier_invoice_number' => ['nullable', 'string', 'max:50'],
            'supplier_invoice_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:300'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.product_unit_id' => ['required', 'integer'],
            'lines.*.lot_number' => ['required', 'string', 'max:50'],
            'lines.*.expires_on' => ['required', 'date'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.free_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ], [], [
            'lines.*.lot_number' => 'batch number',
            'lines.*.expires_on' => 'expiry date',
        ]);

        return [
            [
                'supplier_id' => (int) $data['supplier_id'],
                'purchase_order_id' => isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_invoice_date' => $data['supplier_invoice_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            array_values(array_map(fn (array $line) => [
                'purchase_order_line_id' => isset($line['purchase_order_line_id']) ? (int) $line['purchase_order_line_id'] : null,
                'product_id' => (int) $line['product_id'],
                'product_unit_id' => (int) $line['product_unit_id'],
                'lot_number' => (string) $line['lot_number'],
                'expires_on' => (string) $line['expires_on'],
                'quantity' => (int) $line['quantity'],
                'free_quantity' => (int) ($line['free_quantity'] ?? 0),
                'unit_cost' => (string) $line['unit_cost'],
            ], $data['lines'])),
        ];
    }

    /**
     * @param  iterable<ProductUnit>  $units
     * @return list<array{id: int, name: string, conversion_factor: int}>
     */
    private function unitOptions(iterable $units): array
    {
        $options = [];

        foreach ($units as $unit) {
            if ($unit->is_purchasable || $unit->is_base) {
                $options[] = ['id' => $unit->id, 'name' => $unit->name, 'conversion_factor' => $unit->conversion_factor];
            }
        }

        return $options;
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
