<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->search, fn ($q, $s) => $q->where('display_name', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('customer_code', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_code'       => 'required|string|max:40|unique:customers,customer_code',
            'customer_type'       => 'nullable|in:individual,business',
            'display_name'        => 'required|string|max:180',
            'phone'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:255',
            'tax_number'          => 'nullable|string|max:80',
            'credit_limit'        => 'nullable|numeric|min:0',
            'default_credit_days' => 'nullable|integer|min:0',
            'notes'               => 'nullable|string',
        ]);

        $customer = Customer::create($data);

        return response()->json(['success' => true, 'message' => 'Customer created.', 'data' => $customer], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $customer->load('addresses')]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'customer_code'       => 'sometimes|string|max:40|unique:customers,customer_code,' . $customer->id,
            'customer_type'       => 'nullable|in:individual,business',
            'display_name'        => 'sometimes|string|max:180',
            'phone'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:255',
            'tax_number'          => 'nullable|string|max:80',
            'credit_limit'        => 'nullable|numeric|min:0',
            'default_credit_days' => 'nullable|integer|min:0',
            'is_active'           => 'boolean',
            'notes'               => 'nullable|string',
        ]);

        $customer->update($data);

        return response()->json(['success' => true, 'message' => 'Customer updated.', 'data' => $customer]);
    }

    // Statement: what the customer was billed, paid and still owes
    public function statement(Customer $customer): JsonResponse
    {
        $sales = Sale::where('customer_id', $customer->id)
            ->where('status', 'posted')
            ->withSum('allocations as paid_amount', 'allocated_amount')
            ->orderBy('issued_at')
            ->get()
            ->map(fn ($s) => [
                'sale_id'        => $s->id,
                'sale_number'    => $s->sale_number,
                'issued_at'      => $s->issued_at,
                'due_date'       => $s->due_date,
                'grand_total'    => (float) $s->grand_total,
                'paid'           => (float) $s->paid_amount,
                'balance'        => round((float) $s->grand_total - (float) $s->paid_amount, 2),
                'payment_status' => $s->payment_status,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'customer'     => $customer,
                'total_billed' => round($sales->sum('grand_total'), 2),
                'total_paid'   => round($sales->sum('paid'), 2),
                'balance_due'  => round($sales->sum('balance'), 2),
                'sales'        => $sales,
            ],
        ]);
    }
}