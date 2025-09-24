<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index()
    {
        $products = Product::with(['category', 'images'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products fetched successfully',
            'data' => ['products' => $products],
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'slug'              => 'nullable|string|unique:products,slug',
            'image'             => 'nullable|string',
            'type'              => 'required|in:simple,customizable',
            'short_description' => 'nullable|string',
            'description'       => 'nullable|string',
            'price'             => 'required|numeric',
            'status'            => 'required|boolean',
            'offer_price'       => 'nullable|numeric',
            'category_id'       => 'required|exists:categories,id',
        ]);

        $data = $request->only([
            'name', 'slug', 'image', 'type',
            'short_description', 'description',
            'price', 'status', 'offer_price', 'category_id'
        ]);

        $data['slug'] = $request->slug ?? Str::slug($request->name);

        // 1. Create product
        $product = Product::create($data);

        // 2. If customizable, save customization options
        if ($product->type === 'customizable') {
            $this->storeCustomizations($product, $request);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('category', 'images')
        ], 201);
    }

    /**
     * Show product details with relations.
     */
    public function show($id)
    {
        try {
            $product = Product::with(['category', 'images'])->findOrFail($id);

            if ($product->type === 'customizable') {
                $product->load([
                    'skin_tones.images',
                    'hairs.images',
                    'noses.images',
                    'eyes.images',
                    'mouths.images',
                    'dresses.images',
                    'crowns.images',
                    'base_cards.images',
                    'beards.images',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $product
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Update product.
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $request->validate([
                'name'              => 'sometimes|string|max:255',
                'slug'              => 'nullable|string|unique:products,slug,' . $product->id,
                'image'             => 'nullable|string',
                'type'              => 'sometimes|in:simple,customizable',
                'short_description' => 'nullable|string',
                'description'       => 'nullable|string',
                'price'             => 'sometimes|numeric',
                'status'            => 'sometimes|boolean',
                'offer_price'       => 'nullable|numeric',
                'category_id'       => 'sometimes|exists:categories,id',
            ]);

            $data = $request->only([
                'name', 'slug', 'image', 'type',
                'short_description', 'description',
                'price', 'status', 'offer_price', 'category_id'
            ]);

            if (!empty($data['name']) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $product->update($data);

            if ($product->type === 'customizable') {
                // Optional: clear old customizations before re-adding
                $this->updateCustomizations($product, $request);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Delete product.
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Store customizable options.
     */
    private function storeCustomizations(Product $product, Request $request)
    {
        $relations = [
            'skin_tones', 'hairs', 'noses', 'eyes',
            'mouths', 'dresses', 'crowns', 'base_cards', 'beards'
        ];

        foreach ($relations as $relation) {
            if ($request->filled($relation)) {
                foreach ($request->$relation as $itemData) {
                    $item = $product->{$relation}()->create([
                        'name' => $itemData['name'] ?? null,
                    ]);

                    if (!empty($itemData['images'])) {
                        foreach ($itemData['images'] as $image) {
                            $item->images()->create(['image' => $image]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Update customizations (basic replace strategy).
     */
    private function updateCustomizations(Product $product, Request $request)
    {
        $relations = [
            'skin_tones', 'hairs', 'noses', 'eyes',
            'mouths', 'dresses', 'crowns', 'base_cards', 'beards'
        ];

        foreach ($relations as $relation) {
            if ($request->filled($relation)) {
                // Delete old data
                $product->{$relation}()->delete();

                // Add new data
                foreach ($request->$relation as $itemData) {
                    $item = $product->{$relation}()->create([
                        'name' => $itemData['name'] ?? null,
                    ]);

                    if (!empty($itemData['images'])) {
                        foreach ($itemData['images'] as $image) {
                            $item->images()->create(['image' => $image]);
                        }
                    }
                }
            }
        }
    }
}
