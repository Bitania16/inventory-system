<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'unit'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('product_code', 'like', "%{$search}%");
            })
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->latest()
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_code'    => 'required|string|max:50|unique:products,product_code',
            'name'            => 'required|string|max:180',
            'slug'            => 'nullable|string|max:200|unique:products,slug',
            'description'     => 'nullable|string',
            'category_id'     => 'required|exists:categories,id',
            'brand_id'        => 'nullable|exists:brands,id',
            'base_unit_id'    => 'required|exists:units,id',
            'status'          => 'in:draft,active,inactive,archived',
            'track_inventory' => 'boolean',
            'is_sellable'     => 'boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']) . '-' . Str::lower(Str::random(5));
        $validated['created_by'] = $request->user()->id;

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data'    => $product->load(['category', 'brand', 'unit']),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $product->load(['category', 'brand', 'unit']),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'product_code'    => 'sometimes|string|max:50|unique:products,product_code,' . $product->id,
            'name'            => 'sometimes|string|max:180',
            'slug'            => 'sometimes|string|max:200|unique:products,slug,' . $product->id,
            'description'     => 'nullable|string',
            'category_id'     => 'sometimes|exists:categories,id',
            'brand_id'        => 'nullable|exists:brands,id',
            'base_unit_id'    => 'sometimes|exists:units,id',
            'status'          => 'in:draft,active,inactive,archived',
            'track_inventory' => 'boolean',
            'is_sellable'     => 'boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data'    => $product->load(['category', 'brand', 'unit']),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Product deleted successfully.']);
    }
}