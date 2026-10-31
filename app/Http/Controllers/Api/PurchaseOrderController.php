<?php

namespace App\Http\Controllers\Api;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockTransactionType;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(): JsonResponse
    {
        $orders = PurchaseOrder::with(['supplier', 'store'])->latest()->paginate(15);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $purchaseOrder->load(['supplier', 'store', 'items.variant', 'goodsReceipts.items']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'                   => 'required|exists:suppliers,id',
            'store_id'                      => 'required|exists:stores,id',
            'destination_stock_location_id' => 'required|exists:stock_locations,id',
            'expected_at'                   => 'nullable|date',
            'notes'                         => 'nullable|string',
            'items'                         => 'required|array|min:1',
            'items.*.product_variant_id'    => 'required|exists:product_variants,id',
            'items.*.quantity_ordered'      => 'required|numeric|gt:0',
            'items.*.unit_cost'             => 'required|numeric|min:0',
            'items.*.discount_amount'       => 'nullable|numeric|min:0',
            'items.*.tax_amount'            => 'nullable|numeric|min:0',
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $order = PurchaseOrder::create([
                'purchase_order_number'         => 'PO-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                'supplier_id'                   => $data['supplier_id'],
                'store_id'                      => $data['store_id'],
                'destination_stock_location_id' => $data['destination_stock_location_id'],
                'status'                        => PurchaseOrderStatus::Draft,
                'ordered_at'                    => now(),
                'expected_at'                   => $data['expected_at'] ?? null,
                'created_by'                    => $request->user()->id,
                'notes'                         => $data['notes'] ?? null,
            ]);

            $subtotal = $discount = $tax = 0;

            foreach ($data['items'] as $row) {
                $disc = (float) ($row['discount_amount'] ?? 0);
                $tx   = (float) ($row['tax_amount'] ?? 0);
                $line = ($row['quantity_ordered'] * $row['unit_cost']) - $disc + $tx;

                $order->items()->create([
                    'product_variant_id' => $row['product_variant_id'],
                    'quantity_ordered'   => $row['quantity_ordered'],
                    'unit_cost'          => $row['unit_cost'],
                    'discount_amount'    => $disc,
                    'tax_amount'         => $tx,
                    'line_total'         => $line,
                ]);

                $subtotal += $row['quantity_ordered'] * $row['unit_cost'];
                $discount += $disc;
                $tax      += $tx;
            }

            $order->update([
                'subtotal'       => $subtotal,
                'discount_total' => $discount,
                'tax_total'      => $tax,
                'grand_total'    => $subtotal - $discount + $tax,
            ]);

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase order created.',
            'data'    => $order->load('items'),
        ], 201);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only draft orders can be approved.']);
        }

        $purchaseOrder->update([
            'status'      => PurchaseOrderStatus::Approved,
            'approved_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Purchase order approved.', 'data' => $purchaseOrder]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = $request->validate([
            'supplier_delivery_note'         => 'nullable|string|max:100',
            'notes'                          => 'nullable|string',
            'items'                          => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received'      => 'required|numeric|gt:0',
        ]);

        $receipt = DB::transaction(function () use ($data, $request, $purchaseOrder) {

            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw ValidationException::withMessages(['status' => 'Order must be approved before receiving goods.']);
            }

            $receipt = GoodsReceipt::create([
                'receipt_number'         => 'GR-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                'purchase_order_id'      => $purchaseOrder->id,
                'supplier_id'            => $purchaseOrder->supplier_id,
                'stock_location_id'      => $purchaseOrder->destination_stock_location_id,
                'status'                 => GoodsReceiptStatus::Posted,
                'supplier_delivery_note' => $data['supplier_delivery_note'] ?? null,
                'received_at'            => now(),
                'received_by'            => $request->user()->id,
                'notes'                  => $data['notes'] ?? null,
            ]);

            $stockLines = [];

            foreach ($data['items'] as $i => $row) {
                $poItem = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
                    ->lockForUpdate()
                    ->find($row['purchase_order_item_id']);

                if (! $poItem) {
                    throw ValidationException::withMessages([
                        "items.$i.purchase_order_item_id" => 'This item does not belong to this order.',
                    ]);
                }

                $remaining = (float) $poItem->quantity_ordered - (float) $poItem->quantity_received;

                if ((float) $row['quantity_received'] > $remaining) {
                    throw ValidationException::withMessages([
                        "items.$i.quantity_received" => "Only $remaining still expected for this item.",
                    ]);
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id'     => $poItem->product_variant_id,
                    'quantity_received'      => $row['quantity_received'],
                    'unit_cost'              => $poItem->unit_cost,
                ]);

                $poItem->increment('quantity_received', $row['quantity_received']);

                $stockLines[] = [
                    'stock_location_id'  => $purchaseOrder->destination_stock_location_id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'quantity_delta'     => $row['quantity_received'],
                    'unit_cost'          => $poItem->unit_cost,
                ];
            }

            // Stock goes up through the inventory service (same DB transaction)
            $this->inventory->post(
                StockTransactionType::PurchaseReceipt,
                $stockLines,
                'goods_receipt',
                $receipt->id,
                $request->user()->id
            );

            // Update the order status
            $allReceived = ! $purchaseOrder->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->exists();

            $purchaseOrder->update([
                'status' => $allReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
            ]);

            return $receipt;
        });

        return response()->json([
            'success' => true,
            'message' => 'Goods received. Stock updated.',
            'data'    => $receipt->load('items'),
        ], 201);
    }
}