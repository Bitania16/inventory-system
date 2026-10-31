<?php

namespace App\Http\Controllers\Api;

use App\Enums\CashMovementType;
use App\Enums\CashierShiftStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'           => 'required|exists:customers,id',
            'payment_method_id'     => 'required|exists:payment_methods,id',
            'amount'                => 'required|numeric|gt:0',
            'transaction_reference' => 'nullable|string|max:150',
            'idempotency_key'       => 'nullable|string|max:100|unique:payments,idempotency_key',
            'pos_register_id'       => 'nullable|exists:pos_registers,id',
            'sale_ids'              => 'nullable|array',
            'sale_ids.*'            => 'exists:sales,id',
            'notes'                 => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($data, $request) {

            $userId   = $request->user()->id;
            $customer = Customer::findOrFail($data['customer_id']);
            $method   = PaymentMethod::findOrFail($data['payment_method_id']);
            $amount   = round((float) $data['amount'], 2);

            if (! $method->is_active) {
                throw ValidationException::withMessages(['payment_method_id' => 'Payment method is not active.']);
            }

            if ($method->requires_reference && empty($data['transaction_reference'])) {
                throw ValidationException::withMessages(['transaction_reference' => "{$method->name} requires a transaction reference."]);
            }

            $isCash = $method->method_type === PaymentMethodType::Cash;
            $shift  = null;

            // Cash must go into an open drawer
            if ($isCash) {
                if (empty($data['pos_register_id'])) {
                    throw ValidationException::withMessages(['pos_register_id' => 'Cash payments need a register with an open shift.']);
                }

                $shift = CashierShift::where('pos_register_id', $data['pos_register_id'])
                    ->where('status', CashierShiftStatus::Open->value)
                    ->lockForUpdate()
                    ->first();

                if (! $shift) {
                    throw ValidationException::withMessages(['pos_register_id' => 'No open shift on this register.']);
                }
            }

            // Sales with a balance, oldest first (lock them so two payments cannot overpay)
            $salesQuery = Sale::where('customer_id', $customer->id)
                ->where('status', 'posted')
                ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::PartiallyPaid->value])
                ->when(! empty($data['sale_ids']), fn ($q) => $q->whereIn('id', $data['sale_ids']))
                ->orderBy('issued_at')
                ->lockForUpdate();

            $sales = $salesQuery->get();

            if ($sales->isEmpty()) {
                throw ValidationException::withMessages(['sale_ids' => 'This customer has no unpaid sales.']);
            }

            $balances = [];
            foreach ($sales as $sale) {
                $paid = (float) PaymentAllocation::where('sale_id', $sale->id)->sum('allocated_amount');
                $balances[$sale->id] = round((float) $sale->grand_total - $paid, 2);
            }

            $totalDue = round(array_sum($balances), 2);

            if ($amount > $totalDue + 0.001) {
                throw ValidationException::withMessages(['amount' => "Amount is more than the balance due ($totalDue)."]);
            }

            $payment = Payment::create([
                'payment_number'        => 'PAY-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                'customer_id'           => $customer->id,
                'payment_method_id'     => $method->id,
                'amount'                => $amount,
                'status'                => PaymentRecordStatus::Succeeded,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'idempotency_key'       => $data['idempotency_key'] ?? null,
                'received_at'           => now(),
                'recorded_by'           => $userId,
                'notes'                 => $data['notes'] ?? null,
            ]);

            // Spread the money over the sales, oldest first
            $left = $amount;
            $allocated = [];

            foreach ($sales as $sale) {
                if ($left <= 0.001) break;

                $apply = round(min($left, $balances[$sale->id]), 2);
                if ($apply <= 0) continue;

                PaymentAllocation::create([
                    'payment_id'       => $payment->id,
                    'sale_id'          => $sale->id,
                    'allocated_amount' => $apply,
                    'allocated_at'     => now(),
                    'allocated_by'     => $userId,
                ]);

                $newBalance = round($balances[$sale->id] - $apply, 2);

                $sale->update([
                    'payment_status' => $newBalance <= 0.009 ? PaymentStatus::Paid : PaymentStatus::PartiallyPaid,
                ]);

                $allocated[] = ['sale_number' => $sale->sale_number, 'applied' => $apply, 'balance_left' => $newBalance];
                $left = round($left - $apply, 2);
            }

            if ($isCash) {
                $shift->cashMovements()->create([
                    'movement_type' => CashMovementType::CashIn,
                    'amount'        => $amount,
                    'reason'        => 'Customer payment ' . $payment->payment_number,
                    'payment_id'    => $payment->id,
                    'recorded_by'   => $userId,
                    'occurred_at'   => now(),
                ]);
            }

            return ['payment' => $payment, 'allocated' => $allocated];
        });

        return response()->json([
            'success'     => true,
            'message'     => 'Payment recorded.',
            'allocations' => $result['allocated'],
            'data'        => $result['payment'],
        ], 201);
    }
}