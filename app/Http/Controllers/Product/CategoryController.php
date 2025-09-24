<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     * Fetch all categories
     */
    public function index()
    {
        try {
            $categories = Category::orderBy('id', 'desc')->get();

        $baseUrl = url('/public');
        $categories->transform(function ($category) use ($baseUrl) {
            if (!empty($category->image)) {
                $category->image = $baseUrl .'/'. 'storage/' . ltrim($category->image, '/');
            }
            return $category;
        });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Categories fetched successfully',
                'data'    => [
                    'categories' => $categories
                ]
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Category index failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to fetch categories',
                'error'   => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * POST /api/categories
     * Create a new category
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:191|unique:categories,name',
                'slug' => 'nullable|string|max:191|unique:categories,slug',
                'description' => 'nullable|string',
                'image' => 'nullable|string',
            ]);

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = \Str::slug($data['name']);
            }

            // Handle base64 image
            if ($request->has('image') && $request->image) {
                $data['image'] = $this->handleBase64Image($request->image);
            }

            $category = Category::create($data);

            return response()->json([
                'success' => true,
                'status'  => 201,
                'message' => 'Category created successfully',
                'data'    => [
                    'category' => $category
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);

        } catch (QueryException $e) {
            \Log::error('Category store DB error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Database error occurred',
                'error'   => app()->environment('local') ? $e->getMessage() : null
            ], 500);

        } catch (\Throwable $e) {
            \Log::error('Category store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to create category',
                'error'   => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /api/categories/{id}
     * Show single category
     */
    public function show($id)
    {
        try {
            $category = Category::findOrFail($id);

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Category fetched successfully',
                'data'    => [
                    'category' => $category
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Category not found',
            ], 404);

        } catch (\Throwable $e) {
            \Log::error('Category show failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to fetch category',
                'error'   => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * PUT /api/categories/{id}
     * Update category
     */
    public function update(Request $request, $id)
    {
        try {
            $category = Category::findOrFail($id);

            $data = $request->validate([
                'name' => 'sometimes|required|string|max:191|unique:categories,name,' . $category->id,
                'slug' => 'nullable|string|max:191|unique:categories,slug,' . $category->id,
                'description' => 'nullable|string',
                'image' => 'nullable|string',
            ]);

            if (empty($data['slug']) && isset($data['name'])) {
                $data['slug'] = \Str::slug($data['name']);
            }

            // Handle base64 image
            if ($request->has('image') && $request->image) {
                // Delete old image
                if ($category->image && \Storage::disk('public')->exists($category->image)) {
                    \Storage::disk('public')->delete($category->image);
                }

                $data['image'] = $this->handleBase64Image($request->image);
            }

            $category->update($data);

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Category updated successfully',
                'data'    => [
                    'category' => $category
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Category not found',
            ], 404);

        } catch (\Throwable $e) {
            \Log::error('Category update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to update category',
                'error'   => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * DELETE /api/categories/{id}
     * Delete category
     */
    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);

            // Delete image if exists
            if ($category->image && \Storage::disk('public')->exists($category->image)) {
                \Storage::disk('public')->delete($category->image);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Category deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Category not found',
            ], 404);

        } catch (\Throwable $e) {
            \Log::error('Category delete failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to delete category',
                'error'   => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper: Handle base64 image upload
     */
    private function handleBase64Image($base64Image, $folder = 'categories')
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]);
            $image = str_replace(' ', '+', $image);
            $imageName = uniqid() . '.' . $type;
            $filePath = $folder . '/' . $imageName;

            \Storage::disk('public')->put($filePath, base64_decode($image));
            return $filePath;
        }
        return null;
    }
}
