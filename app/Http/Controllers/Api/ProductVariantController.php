<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $product->variants()->with('taxRates')->get(),
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'sku'                   => 'required|string|max:80|unique:product_variants,sku',
            'barcode'               => 'nullable|string|max:100|unique:product_variants,barcode',
            'variant_name'          => 'nullable|string|max:120',
            'attributes'            => 'nullable|array',
            'cost_price'            => 'required|numeric|min:0',
            'selling_price'         => 'required|numeric|min:0',
            'wholesale_price'       => 'nullable|numeric|min:0',
            'minimum_selling_price' => 'nullable|numeric|min:0',
            'weight'                => 'nullable|numeric|min:0',
            'is_active'             => 'boolean',
            'tax_rate_ids'          => 'nullable|array',
            'tax_rate_ids.*'        => 'exists:tax_rates,id',
        ]);

        $taxRateIds = $validated['tax_rate_ids'] ?? [];
        unset($validated['tax_rate_ids']);

        $variant = $product->variants()->create($validated);
        $variant->taxRates()->sync($taxRateIds);

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully.',
            'data'    => $variant->load('taxRates'),
        ], 201);
    }

    public function show(ProductVariant $variant): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $variant->load(['product', 'taxRates']),
        ]);
    }

    public function update(Request $request, ProductVariant $variant): JsonResponse
    {
        $validated = $request->validate([
            'sku'                   => 'sometimes|string|max:80|unique:product_variants,sku,' . $variant->id,
            'barcode'               => 'nullable|string|max:100|unique:product_variants,barcode,' . $variant->id,
            'variant_name'          => 'nullable|string|max:120',
            'attributes'            => 'nullable|array',
            'cost_price'            => 'sometimes|numeric|min:0',
            'selling_price'         => 'sometimes|numeric|min:0',
            'wholesale_price'       => 'nullable|numeric|min:0',
            'minimum_selling_price' => 'nullable|numeric|min:0',
            'weight'                => 'nullable|numeric|min:0',
            'is_active'             => 'boolean',
            'tax_rate_ids'          => 'nullable|array',
            'tax_rate_ids.*'        => 'exists:tax_rates,id',
        ]);

        $taxRateIds = $validated['tax_rate_ids'] ?? null;
        unset($validated['tax_rate_ids']);

        $variant->update($validated);

        if ($taxRateIds !== null) {
            $variant->taxRates()->sync($taxRateIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully.',
            'data'    => $variant->load('taxRates'),
        ]);
    }

    public function destroy(ProductVariant $variant): JsonResponse
    {
        $variant->delete();

        return response()->json(['success' => true, 'message' => 'Variant deleted successfully.']);
    }
}