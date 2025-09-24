<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    /**
     * Display a listing of products with pagination.
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
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        // Check admin role
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name'              => 'required|string|max:255',
            'slug'              => 'nullable|string|unique:products,slug',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type'              => 'required|in:simple,customizable',
            'short_description' => 'nullable|string',
            'description'       => 'nullable|string',
            'price'             => 'required|numeric',
            'status'            => 'required|boolean',
            'offer_price'       => 'nullable|numeric',
            'category_id'       => 'required|exists:categories,id',
            'skin_tones'        => 'sometimes|array',
            'hairs'             => 'sometimes|array',
            'noses'             => 'sometimes|array',
            'eyes'              => 'sometimes|array',
            'mouths'            => 'sometimes|array',
            'dresses'           => 'sometimes|array',
            'crowns'            => 'sometimes|array',
            'base_cards'        => 'sometimes|array',
            'beards'            => 'sometimes|array',
        ]);

        $data = $request->only([
            'name', 'slug', 'type',
            'short_description', 'description',
            'price', 'status', 'offer_price', 'category_id'
        ]);

        // Generate slug if not provided
        $data['slug'] = $request->slug ?? Str::slug($request->name);

        // Save main product image
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('product-images', 'public');
        }

        $product = Product::create($data);

        // Save customizations if applicable
        if ($product->type === 'customizable') {
            $this->handleCustomizations($product, $request);
        }

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Product created successfully',
            'data'    => $product->load('category', 'images')
        ]);
    }

    /**
     * Show product details with all relations.
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
                'status'  => 200,
                'data'    => $product
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Product not found'
            ]);
        }
    }

    /**
     * Update product details.
     */
    public function update(Request $request, $id)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $product = Product::findOrFail($id);

            $request->validate([
                'name'              => 'sometimes|string|max:255',
                'slug'              => 'nullable|string|unique:products,slug,' . $product->id,
                'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'type'              => 'sometimes|in:simple,customizable',
                'short_description' => 'nullable|string',
                'description'       => 'nullable|string',
                'price'             => 'sometimes|numeric',
                'status'            => 'sometimes|boolean',
                'offer_price'       => 'nullable|numeric',
                'category_id'       => 'sometimes|exists:categories,id',
                'skin_tones'        => 'sometimes|array',
                'hairs'             => 'sometimes|array',
                'noses'             => 'sometimes|array',
                'eyes'              => 'sometimes|array',
                'mouths'            => 'sometimes|array',
                'dresses'           => 'sometimes|array',
                'crowns'            => 'sometimes|array',
                'base_cards'        => 'sometimes|array',
                'beards'            => 'sometimes|array',
            ]);

            $data = $request->only([
                'name', 'slug', 'type',
                'short_description', 'description',
                'price', 'status', 'offer_price', 'category_id'
            ]);

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('product-images', 'public');
            }

            if (!empty($data['name']) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $product->update($data);

            // Update customizations if customizable
            if ($product->type === 'customizable') {
                $this->handleCustomizations($product, $request, true);
            }

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Product updated successfully',
                'data'    => $product->load('category','images')
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Product not found'
            ]);
        }
    }

    /**
     * Delete product.
     */
    public function destroy($id)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Product deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Product not found'
            ]);
        }
    }

    /**
     * Handle customizable options for create/update
     */
    private function handleCustomizations(Product $product, Request $request, $isUpdate = false)
    {
        $relations = [
            'skin_tones', 'hairs', 'noses', 'eyes',
            'mouths', 'dresses', 'crowns', 'base_cards', 'beards'
        ];

        foreach ($relations as $relation) {
            if ($request->has($relation)) {
                if ($isUpdate) {
                    // Clear old data on update
                    $product->{$relation}()->delete();
                }

                foreach ($request->$relation as $itemData) {
                    $item = $product->{$relation}()->create([
                        'name' => $itemData['name'] ?? null,
                    ]);

                    if (!empty($itemData['images'])) {
                        foreach ($itemData['images'] as $image) {
                            $path = $image->store($relation . '-images', 'public');
                            $item->images()->create(['image' => $path]);
                        }
                    }
                }
            }
        }
    }
}
