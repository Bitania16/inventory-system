<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\StockTransactionType;
use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockReservation;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineOrderController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    private function code(string $p): string
    {
        return $p . '-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    public function index(Request $request): JsonResponse
    {
        $q = Order::with(['customer:id,display_name', 'items'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->source, fn ($q, $v) => $q->where('source', $v))
            ->latest();

        return response()->json(['success' => true, 'data' => $q->paginate(15)]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $order->load(['customer', 'items', 'shipments.items'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'                => 'required|exists:customers,id',
            'stock_location_id'          => 'required|exists:stock_locations,id',
            'fulfillment_type'           => 'required|in:pickup,delivery',
            'shipping_address_id'        => 'nullable|exists:customer_addresses,id',
            'shipping_fee'               => 'nullable|numeric|min:0',
            'customer_notes'             => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity'           => 'required|numeric|gt:0',
        ]);

        if ($data['fulfillment_type'] === 'delivery' && empty($data['shipping_address_id'])) {
            throw ValidationException::withMessages(['shipping_address_id' => 'Delivery orders need a shipping address.']);
        }

        $order = DB::transaction(function () use ($data, $request) {
            $location = StockLocation::findOrFail($data['stock_location_id']);
            if (! $location->is_sellable || ! $location->is_active) {
                throw ValidationException::withMessages(['stock_location_id' => 'This location is not sellable.']);
            }

            $address = ! empty($data['shipping_address_id'])
                ? CustomerAddress::where('customer_id', $data['customer_id'])->find($data['shipping_address_id'])
                : null;

            if (! empty($data['shipping_address_id']) && ! $address) {
                throw ValidationException::withMessages(['shipping_address_id' => 'Address does not belong to this customer.']);
            }

            $order = Order::create([
                'order_number'              => $this->code('ORD'),
                'source'                    => 'online',
                'status'                    => OrderStatus::Placed,
                'fulfillment_type'          => $data['fulfillment_type'],
                'customer_id'               => $data['customer_id'],
                'store_id'                  => $location->store_id,
                'stock_location_id'         => $location->id,
                'created_by'                => $request->user()->id,
                'shipping_address_id'       => $address?->id,
                'shipping_address_snapshot' => $address ? json_encode($address->toArray()) : null,
                'customer_notes'            => $data['customer_notes'] ?? null,
                'shipping_fee'              => $data['shipping_fee'] ?? 0,
                'placed_at'                 => now(),
            ]);

            $variants = ProductVariant::with('product')
                ->whereIn('id', collect($data['items'])->pluck('product_variant_id'))->get()->keyBy('id');

            $subtotal = 0;

            foreach ($data['items'] as $i => $row) {
                $variant = $variants[$row['product_variant_id']];
                $qty = (float) $row['quantity'];

                if (! $variant->is_active || ! $variant->product->is_sellable) {
                    throw ValidationException::withMessages(["items.$i.product_variant_id" => "SKU {$variant->sku} is not available."]);
                }

                $balance = StockBalance::where('stock_location_id', $location->id)
                    ->where('product_variant_id', $variant->id)->lockForUpdate()->first();

                $available = $balance ? (float) $balance->quantity_on_hand - (float) $balance->quantity_reserved : 0;

                if (! $balance || $qty > $available) {
                    throw ValidationException::withMessages(["items.$i.quantity" => "Only $available available for {$variant->sku}."]);
                }

                $balance->increment('quantity_reserved', $qty);

                $price = (float) $variant->selling_price;
                $line  = round($qty * $price, 2);
                $subtotal += $line;

                $item = $order->items()->create([
                    'product_variant_id'    => $variant->id,
                    'product_name_snapshot' => $variant->product->name . ($variant->variant_name ? ' - ' . $variant->variant_name : ''),
                    'sku_snapshot'          => $variant->sku,
                    'quantity_ordered'      => $qty,
                    'unit_price'            => $price,
                    'line_total'            => $line,
                ]);

                StockReservation::create([
                    'order_item_id'      => $item->id,
                    'stock_location_id'  => $location->id,
                    'product_variant_id' => $variant->id,
                    'quantity'           => $qty,
                    'status'             => ReservationStatus::Active,
                    'expires_at'         => now()->addDays(2),
                    'created_by'         => $request->user()->id,
                ]);
            }

            $order->update([
                'subtotal'    => $subtotal,
                'grand_total' => $subtotal + (float) ($data['shipping_fee'] ?? 0),
            ]);

            return $order;
        });

        return response()->json(['success' => true, 'message' => 'Order placed. Stock reserved.', 'data' => $order->load('items')], 201);
    }

    public function confirm(Order $order): JsonResponse
    {
               if ($this->statusOf($order) !== 'placed') {
            throw ValidationException::withMessages(['status' => 'Only placed orders can be confirmed.']);
        }
        $order->update(['status' => OrderStatus::Confirmed, 'confirmed_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Order confirmed.', 'data' => $order]);
    }

    public function ship(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'shipping_provider' => 'nullable|string|max:100',
            'tracking_number'   => 'nullable|string|max:150',
            'notes'             => 'nullable|string',
        ]);

        $shipment = DB::transaction(function () use ($data, $request, $order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                        if (! in_array($this->statusOf($order), ['confirmed', 'processing'], true)) {
                throw ValidationException::withMessages(['status' => 'Order must be confirmed before shipping.']);
            }

            $reservations = StockReservation::whereIn('order_item_id', $order->items()->pluck('id'))
                ->where('status', ReservationStatus::Active->value)->lockForUpdate()->get();

            if ($reservations->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'No active reservations to ship.']);
            }

            $lines = $reservations->map(fn ($r) => [
                'stock_location_id'  => $r->stock_location_id,
                'product_variant_id' => $r->product_variant_id,
                'quantity_delta'     => -1 * (float) $r->quantity,
            ])->all();

            $this->inventory->post(StockTransactionType::SaleIssue, $lines, 'order', $order->id, $request->user()->id);

            $shipment = Shipment::create([
                'shipment_number'           => $this->code('SHP'),
                'order_id'                  => $order->id,
                'status'                    => 'shipped',
                'shipping_provider'         => $data['shipping_provider'] ?? null,
                'tracking_number'           => $data['tracking_number'] ?? null,
                'recipient_name'            => $order->customer?->display_name,
                'recipient_phone'           => $order->customer?->phone,
                'delivery_address_snapshot' => $order->shipping_address_snapshot,
                'shipped_at'                => now(),
                'handled_by'                => $request->user()->id,
                'notes'                     => $data['notes'] ?? null,
            ]);

            foreach ($reservations as $r) {
                StockBalance::where('stock_location_id', $r->stock_location_id)
                    ->where('product_variant_id', $r->product_variant_id)
                    ->decrement('quantity_reserved', $r->quantity);

                $r->update(['status' => ReservationStatus::Consumed, 'consumed_at' => now()]);

                $r->orderItem->update(['quantity_fulfilled' => $r->quantity]);

                $shipment->items()->create([
                    'order_item_id'    => $r->order_item_id,
                    'quantity_shipped' => $r->quantity,
                ]);
            }

            $order->update(['status' => OrderStatus::Shipped]);

            return $shipment;
        });

        return response()->json(['success' => true, 'message' => 'Order shipped. Stock updated.', 'data' => $shipment->load('items')], 201);
    }

    public function deliver(Shipment $shipment): JsonResponse
    {
        if ($shipment->status !== 'shipped') {
            throw ValidationException::withMessages(['status' => 'Only shipped shipments can be delivered.']);
        }

        DB::transaction(function () use ($shipment) {
            $shipment->update(['status' => 'delivered', 'delivered_at' => now()]);
            $shipment->order->update(['status' => OrderStatus::Completed]);
        });

        return response()->json(['success' => true, 'message' => 'Shipment delivered.', 'data' => $shipment->fresh()]);
    }

    public function cancel(Order $order): JsonResponse
    {
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                       if (! in_array($this->statusOf($order), ['placed', 'confirmed', 'processing'], true)) {
                throw ValidationException::withMessages(['status' => 'This order can no longer be cancelled.']);
            }

            $reservations = StockReservation::whereIn('order_item_id', $order->items()->pluck('id'))
                ->where('status', ReservationStatus::Active->value)->lockForUpdate()->get();

            foreach ($reservations as $r) {
                StockBalance::where('stock_location_id', $r->stock_location_id)
                    ->where('product_variant_id', $r->product_variant_id)
                    ->decrement('quantity_reserved', $r->quantity);

                $r->update(['status' => ReservationStatus::Released, 'released_at' => now()]);
            }

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
        });

        return response()->json(['success' => true, 'message' => 'Order cancelled. Stock released.']);
    }
    private function statusOf($order): string
{
    return $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status;
}
}