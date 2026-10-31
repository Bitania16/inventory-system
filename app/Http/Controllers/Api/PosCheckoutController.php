<?php

namespace App\Http\Controllers\Api;

use App\Enums\CashierShiftStatus;
use App\Enums\CashMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\StockTransactionType;
use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\PosRegister;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCheckoutController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function show(Sale $sale): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $sale->load(['items', 'allocations.payment.method', 'customer']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pos_register_id'                  => 'required|exists:pos_registers,id',
            'stock_location_id'                => 'nullable|exists:stock_locations,id',
            'customer_id'                      => 'nullable|exists:customers,id',
            'idempotency_key'                  => 'nullable|string|max:90',
            'notes'                            => 'nullable|string',
            'items'                            => 'required|array|min:1',
            'items.*.product_variant_id'       => 'required|exists:product_variants,id',
            'items.*.quantity'                 => 'required|numeric|gt:0',
            'items.*.discount_amount'          => 'nullable|numeric|min:0',
            'payments'                         => 'nullable|array',
            'payments.*.payment_method_id'     => 'required|exists:payment_methods,id',
            'payments.*.amount'                => 'required|numeric|gt:0',
            'payments.*.transaction_reference' => 'nullable|string|max:150',
        ]);

        // Double-click protection: same key returns the same sale
        if (! empty($data['idempotency_key'])) {
            $existing = Payment::where('idempotency_key', $data['idempotency_key'] . '-1')->first();
            $sale = $existing?->allocations()->first()?->sale;
            if ($sale) {
                return response()->json([
                    'success' => true,
                    'message' => 'Duplicate request. Existing sale returned.',
                    'data'    => $sale->load(['items', 'allocations']),
                ]);
            }
        }

        $result = DB::transaction(function () use ($data, $request) {

            $userId = $request->user()->id;

            // 1. Register, open shift, stock location
            $register = PosRegister::findOrFail($data['pos_register_id']);

            if (! $register->is_active) {
                throw ValidationException::withMessages(['pos_register_id' => 'Register is not active.']);
            }

            $shift = CashierShift::where('pos_register_id', $register->id)
                ->where('status', CashierShiftStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if (! $shift) {
                throw ValidationException::withMessages(['pos_register_id' => 'No open shift on this register. Open a shift first.']);
            }

            $location = isset($data['stock_location_id'])
                ? StockLocation::findOrFail($data['stock_location_id'])
                : StockLocation::where('store_id', $register->store_id)
                    ->where('is_sellable', true)->where('is_active', true)
                    ->orderBy('id')->first();

            if (! $location || $location->store_id !== $register->store_id || ! $location->is_sellable) {
                throw ValidationException::withMessages(['stock_location_id' => 'No sellable stock location for this store.']);
            }

            // 2. Price the lines (server-side prices)
            $variants = ProductVariant::with(['product.unit', 'taxRates'])
                ->whereIn('id', collect($data['items'])->pluck('product_variant_id'))
                ->get()->keyBy('id');

            $lines = [];
            $subtotal = $discountTotal = $taxTotal = $grandTotal = 0.0;

            foreach ($data['items'] as $i => $row) {
                $variant = $variants[$row['product_variant_id']];
                $product = $variant->product;
                $qty     = (float) $row['quantity'];

                if (! $variant->is_active || ! $product->is_sellable || $product->status !== 'active') {
                    throw ValidationException::withMessages(["items.$i.product_variant_id" => "SKU {$variant->sku} is not available for sale."]);
                }

                if (! $product->unit->allows_decimal_quantity && floor($qty) != $qty) {
                    throw ValidationException::withMessages(["items.$i.quantity" => "SKU {$variant->sku} must be sold in whole units."]);
                }

                $price    = (float) $variant->selling_price;
                $gross    = round($qty * $price, 2);
                $discount = min((float) ($row['discount_amount'] ?? 0), $gross);
                $net      = $gross - $discount;

                if ($variant->minimum_selling_price > 0 && ($net / $qty) < (float) $variant->minimum_selling_price) {
                    throw ValidationException::withMessages(["items.$i.discount_amount" => "Discount too high. Minimum price for {$variant->sku} is {$variant->minimum_selling_price}."]);
                }

                // Tax (only active rates inside their date range)
                $incl = $excl = 0.0;
                foreach ($variant->taxRates as $rate) {
                    if (! $rate->is_active) continue;
                    if ($rate->effective_from && now()->lt($rate->effective_from)) continue;
                    if ($rate->effective_to && now()->gt($rate->effective_to->endOfDay())) continue;
                    $rate->is_inclusive ? $incl += (float) $rate->rate_percent : $excl += (float) $rate->rate_percent;
                }

                $taxIncl = $incl > 0 ? round($net * $incl / (100 + $incl), 2) : 0.0;
                $taxExcl = round($net * $excl / 100, 2);
                $lineTax = $taxIncl + $taxExcl;
                $lineTotal = $net + $taxExcl;

                $subtotal      += $gross;
                $discountTotal += $discount;
                $taxTotal      += $lineTax;
                $grandTotal    += $lineTotal;

                $lines[] = compact('variant', 'qty', 'price', 'discount', 'lineTax', 'lineTotal') + [
                    'rate' => $incl + $excl,
                    'name' => $product->name . ($variant->variant_name ? ' - ' . $variant->variant_name : ''),
                ];
            }

            $grandTotal = round($grandTotal, 2);

            // 3. Work out payments, change and credit
            $methods  = PaymentMethod::whereIn('id', collect($data['payments'] ?? [])->pluck('payment_method_id'))->get()->keyBy('id');
            $payments = collect($data['payments'] ?? []);

            foreach ($payments as $i => $p) {
                $m = $methods[$p['payment_method_id']];
                if (! $m->is_active) {
                    throw ValidationException::withMessages(["payments.$i.payment_method_id" => "{$m->name} is not active."]);
                }
                if ($m->requires_reference && empty($p['transaction_reference'])) {
                    throw ValidationException::withMessages(["payments.$i.transaction_reference" => "{$m->name} requires a transaction reference."]);
                }
            }

            $isCash = fn ($p) => $methods[$p['payment_method_id']]->method_type === PaymentMethodType::Cash;

            $noncash = round((float) $payments->reject($isCash)->sum('amount'), 2);
            $cash    = round((float) $payments->filter($isCash)->sum('amount'), 2);

            if ($noncash > $grandTotal + 0.001) {
                throw ValidationException::withMessages(['payments' => 'Non-cash payments cannot be more than the total.']);
            }

            $cashNeeded = $grandTotal - $noncash;
            $change     = round(max(0, $cash - $cashNeeded), 2);
            $paidTotal  = round($noncash + min($cash, $cashNeeded), 2);
            $due        = round($grandTotal - $paidTotal, 2);

            $customer = ! empty($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : null;

            if ($due > 0.009) {
                if (! $customer) {
                    throw ValidationException::withMessages(['payments' => 'Payment is less than the total. Walk-in customers must pay in full, or choose a customer for credit.']);
                }

                $billed = (float) Sale::where('customer_id', $customer->id)->where('status', SaleStatus::Posted->value)->sum('grand_total');
                $paid   = (float) PaymentAllocation::whereHas('sale', fn ($q) => $q->where('customer_id', $customer->id)->where('status', SaleStatus::Posted->value))->sum('allocated_amount');

                if (($billed - $paid) + $due > (float) $customer->credit_limit) {
                    throw ValidationException::withMessages(['customer_id' => 'Credit limit exceeded for this customer.']);
                }
            }

            // 4. Order + items
            $order = Order::create([
                'order_number'      => 'ORD-' . now()->format('Ymd') . '-' . $this->code(),
                'source'            => 'pos',
                'status'            => OrderStatus::Completed,
                'fulfillment_type'  => 'counter',
                'customer_id'       => $customer?->id,
                'store_id'          => $register->store_id,
                'stock_location_id' => $location->id,
                'pos_register_id'   => $register->id,
                'cashier_shift_id'  => $shift->id,
                'created_by'        => $userId,
                'subtotal'          => $subtotal,
                'discount_total'    => $discountTotal,
                'tax_total'         => $taxTotal,
                'grand_total'       => $grandTotal,
                'customer_notes'    => $data['notes'] ?? null,
                'placed_at'         => now(),
                'confirmed_at'      => now(),
            ]);

            // 5. Sale
            $paymentStatus = $due <= 0.009 ? PaymentStatus::Paid
                : ($paidTotal > 0 ? PaymentStatus::PartiallyPaid : PaymentStatus::Unpaid);

            $terms = $due > 0.009 ? (int) $customer->default_credit_days : null;

            $sale = Sale::create([
                'sale_number'       => 'SAL-' . now()->format('Ymd') . '-' . $this->code(),
                'order_id'          => $order->id,
                'customer_id'       => $customer?->id,
                'store_id'          => $register->store_id,
                'stock_location_id' => $location->id,
                'cashier_shift_id'  => $shift->id,
                'cashier_id'        => $userId,
                'status'            => SaleStatus::Posted,
                'payment_status'    => $paymentStatus,
                'subtotal'          => $subtotal,
                'discount_total'    => $discountTotal,
                'tax_total'         => $taxTotal,
                'grand_total'       => $grandTotal,
                'credit_terms_days' => $terms,
                'due_date'          => $terms !== null ? now()->addDays($terms)->toDateString() : null,
                'issued_at'         => now(),
                'notes'             => $data['notes'] ?? null,
            ]);

            $stockLines = [];

            foreach ($lines as $l) {
                $orderItem = $order->items()->create([
                    'product_variant_id'    => $l['variant']->id,
                    'product_name_snapshot' => $l['name'],
                    'sku_snapshot'          => $l['variant']->sku,
                    'quantity_ordered'      => $l['qty'],
                    'quantity_fulfilled'    => $l['qty'],
                    'unit_price'            => $l['price'],
                    'discount_amount'       => $l['discount'],
                    'tax_rate_percent'      => $l['rate'],
                    'tax_amount'            => $l['lineTax'],
                    'line_total'            => $l['lineTotal'],
                ]);

                $sale->items()->create([
                    'order_item_id'         => $orderItem->id,
                    'product_variant_id'    => $l['variant']->id,
                    'product_name_snapshot' => $l['name'],
                    'sku_snapshot'          => $l['variant']->sku,
                    'quantity'              => $l['qty'],
                    'unit_price'            => $l['price'],
                    'discount_amount'       => $l['discount'],
                    'tax_rate_percent'      => $l['rate'],
                    'tax_amount'            => $l['lineTax'],
                    'line_total'            => $l['lineTotal'],
                ]);

                $stockLines[] = [
                    'stock_location_id'  => $location->id,
                    'product_variant_id' => $l['variant']->id,
                    'quantity_delta'     => -1 * $l['qty'],
                    'unit_cost'          => $l['variant']->cost_price,
                ];
            }

            // 6. Stock goes down (throws if not enough stock, which rolls everything back)
            $this->inventory->post(StockTransactionType::SaleIssue, $stockLines, 'sale', $sale->id, $userId);

            // 7. Payments: non-cash first, then cash
            $remaining = $grandTotal;
            $n = 0;

            foreach ($payments->sortBy(fn ($p) => $isCash($p) ? 1 : 0) as $p) {
                $applied = round(min((float) $p['amount'], $remaining), 2);
                if ($applied <= 0) continue;

                $remaining = round($remaining - $applied, 2);
                $n++;
                $method = $methods[$p['payment_method_id']];

                $payment = Payment::create([
                    'payment_number'        => 'PAY-' . now()->format('Ymd') . '-' . $this->code(),
                    'customer_id'           => $customer?->id,
                    'payment_method_id'     => $method->id,
                    'amount'                => $applied,
                    'status'                => PaymentRecordStatus::Succeeded,
                    'transaction_reference' => $p['transaction_reference'] ?? null,
                    'idempotency_key'       => ! empty($data['idempotency_key']) ? $data['idempotency_key'] . '-' . $n : null,
                    'received_at'           => now(),
                    'recorded_by'           => $userId,
                    'notes'                 => $change > 0 && $isCash($p) ? "Change given: $change" : null,
                ]);

                PaymentAllocation::create([
                    'payment_id'       => $payment->id,
                    'sale_id'          => $sale->id,
                    'allocated_amount' => $applied,
                    'allocated_at'     => now(),
                    'allocated_by'     => $userId,
                ]);

                if ($method->method_type === PaymentMethodType::Cash) {
                    $shift->cashMovements()->create([
                        'movement_type' => CashMovementType::CashIn,
                        'amount'        => $applied,
                        'reason'        => 'Sale ' . $sale->sale_number,
                        'payment_id'    => $payment->id,
                        'recorded_by'   => $userId,
                        'occurred_at'   => now(),
                    ]);
                }
            }

            return ['sale' => $sale, 'change' => $change, 'due' => $due];
        });

        return response()->json([
            'success'      => true,
            'message'      => 'Sale completed.',
            'change_given' => $result['change'],
            'amount_due'   => $result['due'],
            'data'         => $result['sale']->load(['items', 'allocations.payment']),
        ], 201);
    }

    private function code(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}