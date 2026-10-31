<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Category::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('slug')) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }

        $validated = $request->validate([
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'code'        => ['nullable', 'string', 'max:40', 'unique:categories,code'],
            'name'        => ['required', 'string', 'max:120'],
            'slug'        => ['required', 'string', 'max:150', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['boolean'],
        ]);

        $validated['created_by'] = $request->user()->id;

        $category = Category::create($validated);

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $category->load('children')]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        if ($request->has('slug')) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }

        $validated = $request->validate([
            'parent_id'   => ['nullable', 'exists:categories,id', Rule::notIn([$category->id])],
            'code'        => ['nullable', 'string', 'max:40', Rule::unique('categories', 'code')->ignore($category->id)],
            'name'        => ['sometimes', 'string', 'max:120'],
            'slug'        => ['sometimes', 'string', 'max:150', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['boolean'],
        ]);

        $category->update($validated);

        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted successfully.']);
    }
}