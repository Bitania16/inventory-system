<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Instantiate a new controller instance.
     */
    public function __construct()
    {
        // Require authentication via Sanctum for all methods
        $this->middleware('auth:sanctum');

        // Sales, Manager, and Admin can READ (index, show)
        $this->middleware('role:admin|manager|sales')->only(['index', 'show']);

        // Only Admin and Manager can CREATE, UPDATE, and DELETE
        $this->middleware('role:admin|manager')->only(['store', 'update', 'destroy']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Category::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Clean and format user's explicitly provided slug (e.g., "My Custom Slug" -> "my-custom-slug")
        if ($request->has('slug')) {
            $request->merge([
                'slug' => Str::slug($request->input('slug'))
            ]);
        }

        // Validate that 'slug' is explicitly provided and unique
        $validated = $request->validate([
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        // Format the slug if sent in update request
        if ($request->has('slug')) {
            $request->merge([
                'slug' => Str::slug($request->input('slug'))
            ]);
        }

        $validated = $request->validate([
            'parent_id'   => ['nullable', 'exists:categories,id', 'different:id'],
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'slug'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.'
        ]);
    }
}