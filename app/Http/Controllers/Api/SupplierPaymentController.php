<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = SupplierPayment::query()
            ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($request->purchase_order_id, fn ($q, $v) => $q->where('purchase_order_id', $v))
            ->latest();

        return response()->json(['success' => true, 'data' => $q->paginate(15)]);
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $supplierPayment->load(['supplier', 'method'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'           => 'required|exists:suppliers,id',
            'purchase_order_id'     => 'nullable|exists:purchase_orders,id',
            'payment_method_id'     => 'required|exists:payment_methods,id',
            'amount'                => 'required|numeric|gt:0',
            'transaction_reference' => 'nullable|string|max:150',
            'notes'                 => 'nullable|string',
        ]);

        $payment = DB::transaction(function () use ($data, $request) {
            if (! empty($data['purchase_order_id'])) {
                $po = PurchaseOrder::whereKey($data['purchase_order_id'])->lockForUpdate()->firstOrFail();

                if ($po->supplier_id != $data['supplier_id']) {
                    throw ValidationException::withMessages(['purchase_order_id' => 'This order belongs to another supplier.']);
                }

                $paid = (float) SupplierPayment::where('purchase_order_id', $po->id)
                    ->where('status', PaymentRecordStatus::Succeeded->value)->sum('amount');
                $left = round((float) $po->grand_total - $paid, 2);

                if ((float) $data['amount'] > $left + 0.001) {
                    throw ValidationException::withMessages(['amount' => "Only $left is still unpaid on this order."]);
                }
            }

            return SupplierPayment::create($data + [
                'payment_number' => 'SPAY-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                'status'         => PaymentRecordStatus::Succeeded,
                'paid_at'        => now(),
                'recorded_by'    => $request->user()->id,
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Supplier payment recorded.', 'data' => $payment], 201);
    }
}