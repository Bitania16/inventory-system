<?php

namespace App\Http\Controllers\Api;

use App\Enums\CashierShiftStatus;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\StockTransactionType;
use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleRefund;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    private function code(string $p): string
    {
        return $p . '-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => SaleReturn::with('items')->latest()->paginate(15)]);
    }

    public function show(SaleReturn $return): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $return->load(['items', 'refunds', 'sale'])]);
    }

    private function alreadyReturned(int $saleItemId): float
    {
        return (float) SaleReturnItem::where('sale_item_id', $saleItemId)
            ->whereHas('saleReturn', fn ($q) => $q->whereNotIn('status', [ReturnStatus::Rejected->value, ReturnStatus::Cancelled->value]))
            ->sum('quantity');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id'                => 'required|exists:sales,id',
            'reason'                 => 'nullable|string',
            'restock_to_location_id' => 'nullable|exists:stock_locations,id',
            'items'                  => 'required|array|min:1',
            'items.*.sale_item_id'   => 'required|exists:sale_items,id',
            'items.*.quantity'       => 'required|numeric|gt:0',
            'items.*.condition'      => 'nullable|string|max:50',
            'items.*.restock'        => 'boolean',
        ]);

        $return = DB::transaction(function () use ($data) {
            $sale = Sale::whereKey($data['sale_id'])->lockForUpdate()->firstOrFail();

            if (! in_array($sale->status, [SaleStatus::Posted, SaleStatus::PartiallyReturned], true)) {
                throw ValidationException::withMessages(['sale_id' => 'This sale cannot be returned.']);
            }

            $return = SaleReturn::create([
                'return_number'          => $this->code('RET'),
                'sale_id'                => $sale->id,
                'customer_id'            => $sale->customer_id,
                'status'                 => ReturnStatus::Requested,
                'reason'                 => $data['reason'] ?? null,
                'requested_at'           => now(),
                'restock_to_location_id' => $data['restock_to_location_id'] ?? $sale->stock_location_id,
                'refund_total'           => 0,
            ]);

            $total = 0;
            foreach ($data['items'] as $i => $row) {
                $item = SaleItem::where('sale_id', $sale->id)->find($row['sale_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(["items.$i.sale_item_id" => 'This item is not part of the sale.']);
                }

                $left = (float) $item->quantity - $this->alreadyReturned($item->id);
                if ((float) $row['quantity'] > $left + 0.0001) {
                    throw ValidationException::withMessages(["items.$i.quantity" => "Only $left can still be returned."]);
                }

                $refund = round((float) $item->line_total / (float) $item->quantity * (float) $row['quantity'], 2);
                $total += $refund;

                $return->items()->create([
                    'sale_item_id'       => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity'           => $row['quantity'],
                    'condition'          => $row['condition'] ?? null,
                    'restock'            => $row['restock'] ?? true,
                    'refund_amount'      => $refund,
                ]);
            }

            $return->update(['refund_total' => $total]);

            return $return;
        });

        return response()->json(['success' => true, 'message' => 'Return requested.', 'data' => $return->load('items')], 201);
    }

    public function approve(Request $request, SaleReturn $return): JsonResponse
    {
        if ($return->status !== ReturnStatus::Requested) {
            throw ValidationException::withMessages(['status' => 'Only requested returns can be approved.']);
        }
        $return->update(['status' => ReturnStatus::Approved, 'processed_by' => $request->user()->id]);

        return response()->json(['success' => true, 'message' => 'Return approved.', 'data' => $return]);
    }

    public function reject(Request $request, SaleReturn $return): JsonResponse
    {
        if ($return->status !== ReturnStatus::Requested) {
            throw ValidationException::withMessages(['status' => 'Only requested returns can be rejected.']);
        }
        $return->update(['status' => ReturnStatus::Rejected, 'processed_by' => $request->user()->id]);

        return response()->json(['success' => true, 'message' => 'Return rejected.', 'data' => $return]);
    }

    public function receive(Request $request, SaleReturn $return): JsonResponse
    {
        DB::transaction(function () use ($request, $return) {
            $return = SaleReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->status !== ReturnStatus::Approved) {
                throw ValidationException::withMessages(['status' => 'Only approved returns can be received.']);
            }

            $lines = [];
            foreach ($return->items as $item) {
                if ($item->restock) {
                    $lines[] = [
                        'stock_location_id'  => $return->restock_to_location_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity_delta'     => (float) $item->quantity,
                    ];
                }
            }

            if ($lines) {
                $this->inventory->post(StockTransactionType::CustomerReturn, $lines, 'sale_return', $return->id, $request->user()->id);
            }

            $return->update(['status' => ReturnStatus::Received, 'received_at' => now(), 'processed_by' => $request->user()->id]);

            $sale = $return->sale;
            $sold = (float) $sale->items()->sum('quantity');
            $back = (float) SaleReturnItem::whereHas('saleReturn', fn ($q) => $q->where('sale_id', $sale->id)
                ->whereIn('status', [ReturnStatus::Received->value, ReturnStatus::Refunded->value, ReturnStatus::Completed->value]))
                ->sum('quantity');

            $sale->update(['status' => $back >= $sold ? SaleStatus::Returned : SaleStatus::PartiallyReturned]);
        });

        return response()->json(['success' => true, 'message' => 'Return received.', 'data' => $return->fresh('items')]);
    }

    public function refund(Request $request, SaleReturn $return): JsonResponse
    {
        $data = $request->validate([
            'payment_method_id'     => 'required|exists:payment_methods,id',
            'amount'                => 'required|numeric|gt:0',
            'transaction_reference' => 'nullable|string|max:150',
            'pos_register_id'       => 'nullable|exists:pos_registers,id',
            'notes'                 => 'nullable|string',
        ]);

        $refund = DB::transaction(function () use ($data, $request, $return) {
            $return = SaleReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (! in_array($return->status, [ReturnStatus::Received, ReturnStatus::Refunded], true)) {
                throw ValidationException::withMessages(['status' => 'Goods must be received before refunding.']);
            }

            $method = PaymentMethod::findOrFail($data['payment_method_id']);
            $amount = round((float) $data['amount'], 2);

            if ($method->requires_reference && empty($data['transaction_reference'])) {
                throw ValidationException::withMessages(['transaction_reference' => "{$method->name} requires a transaction reference."]);
            }

            $done = (float) $return->refunds()->where('status', RefundStatus::Succeeded->value)->sum('amount');
            $left = round((float) $return->refund_total - $done, 2);

            if ($amount > $left + 0.001) {
                throw ValidationException::withMessages(['amount' => "Only $left can still be refunded."]);
            }

            $shift = null;
            if ($method->method_type === PaymentMethodType::Cash) {
                $shift = CashierShift::where('pos_register_id', $data['pos_register_id'] ?? 0)
                    ->where('status', CashierShiftStatus::Open->value)->lockForUpdate()->first();
                if (! $shift) {
                    throw ValidationException::withMessages(['pos_register_id' => 'Cash refunds need a register with an open shift.']);
                }
            }

            $refund = SaleRefund::create([
                'refund_number'         => $this->code('REF'),
                'sale_return_id'        => $return->id,
                'payment_method_id'     => $method->id,
                'amount'                => $amount,
                'status'                => RefundStatus::Succeeded,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'refunded_at'           => now(),
                'processed_by'          => $request->user()->id,
                'notes'                 => $data['notes'] ?? null,
            ]);

            if ($shift) {
                $shift->cashMovements()->create([
                    'movement_type' => CashMovementType::CashOut,
                    'amount'        => $amount,
                    'reason'        => 'Refund ' . $refund->refund_number,
                    'recorded_by'   => $request->user()->id,
                    'occurred_at'   => now(),
                ]);
            }

            $fully = ($done + $amount) >= (float) $return->refund_total - 0.001;
            $return->update(['status' => $fully ? ReturnStatus::Completed : ReturnStatus::Refunded]);

            $sale = $return->sale;
            $refunded = (float) SaleRefund::where('status', RefundStatus::Succeeded->value)
                ->whereHas('saleReturn', fn ($q) => $q->where('sale_id', $sale->id))->sum('amount');
            if (in_array($sale->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                $sale->update(['payment_status' => $refunded >= (float) $sale->grand_total - 0.001 ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded]);
            }

            return $refund;
        });

        return response()->json(['success' => true, 'message' => 'Refund recorded.', 'data' => $refund], 201);
    }
}