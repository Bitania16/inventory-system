<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use App\Models\StockTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function balances(Request $request): JsonResponse
    {
        $q = StockBalance::with(['stockLocation:id,code,name', 'variant:id,sku,variant_name'])
            ->when($request->stock_location_id, fn ($q, $v) => $q->where('stock_location_id', $v))
            ->when($request->product_variant_id, fn ($q, $v) => $q->where('product_variant_id', $v))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'reorder_level'));

        return response()->json(['success' => true, 'data' => $q->orderBy('id')->paginate(20)]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $q = StockTransaction::with('items')
            ->when($request->transaction_type, fn ($q, $v) => $q->where('transaction_type', $v))
            ->latest('id');

        return response()->json(['success' => true, 'data' => $q->paginate(20)]);
    }
}